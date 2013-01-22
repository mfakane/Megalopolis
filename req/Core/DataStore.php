<?php
abstract class DataStore
{
	private $handles = array();
	private $tableNames = array();
	
	protected function registerHandle(PDO &$db, $name)
	{
		$this->handles[$name] = &$db;
	}
	
	protected function unregisterHandle(PDO &$db)
	{
		unset($this->handles[$this->getDatabaseNameByHandle($db)]);
	}
	
	protected function getDatabaseNameByHandle(PDO &$db)
	{
		return array_search($db, $this->handles, true);
	}
	
	protected function getHandleByName($name)
	{
		return isset($this->handles[$name]) ? $this->handles[$name] : null;
	}
	
	protected function registerTableByHandle(PDO &$db, $name)
	{
		$this->tableNames[$this->getDatabaseNameByHandle($db)][] = $name;
	}
	
	protected function unregisterTableByHandle(PDO &$db, $name)
	{
		$arr = &$this->tableNames[$this->getDatabaseNameByHandle($db)];
		
		unset($arr[array_search($name, $arr)]);
	}
	
	/**
	 * @param string $database
	 * @return PDO
	 */
	abstract function open($database = "data");
	
	/**
	 * @param bool $vacuum [optional]
	 */
	abstract function close(PDO &$db, $vacuum = false);
	
	/**
	 * @return array|string
	 */
	abstract function getTables(PDO $db);
	
	/**
	 * @param string $name
	 */
	function hasTable(PDO $db, $name)
	{
		$dbname = $this->getDatabaseNameByHandle($db);
		
		if (!isset($this->tableNames[$dbname]))
			$this->tableNames[$dbname] = array_map("strtolower", $this->getTables($db));
		
		return in_array(strtolower($name), $this->tableNames[$dbname]);
	}
	
	/**
	 * @param PDOStatement $st
	 * @return PDOStatement
	 */
	function ensureStatement(PDO $db, $st, $throw = true)
	{
		if ($st)
			return $st;
		else if ($throw)
		{
			$message = implode(":", $db->errorInfo());
			
			throw new ApplicationException($message);
		}
		else
			return null;
	}
	
	/**
	 * @param bool $throw [optional]
	 * @return bool
	 */
	function executeStatement(PDOStatement $st, array $params = null, $throw = true)
	{
		if (!$st)
			return false;
		
		foreach(range(1, 5) as $i)
		{
			if (is_null($params))
				$rt = $st->execute();
			else
				$rt = $st->execute($params);
			
			if ($rt)
				break;
			
			$error = $st->errorInfo();
			
			if ($error != array("HY000", 5, "database is locked") &&
				$error != array("HY000", 21, "library routine called out of sequence"))
				break;
			
			usleep(5000);
		}
		
		$rt = $rt || $st->errorCode() == PDO::ERR_NONE;
		
		if ($rt)
			return $rt;
		else if ($throw)
		{
			$message = implode(":", $st->errorInfo());
			
			if (defined("SQL_DEBUG") && SQL_DEBUG)
				$message .= "\r\n" . $st->queryString;
			
			throw new ApplicationException($message);
		}
		else
			return false;
	}
	
	/**
	 * @param string $name
	 */
	function createTableIfNotExists(PDO $db, array $schema, $name, array $index = null)
	{
		if (!$this->hasTable($db, $name))
		{
			$arr = array_map(create_function('$x, $y', 'return "{$x} {$y}";'), array_keys($schema), array_values($schema));

			$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf
			("
				create table if not exists %s
				(
					%s,
					primary key(%s)
				)",
				$name,
				implode(", ", array_map(create_function('$_', 'return strtr($_, array(" primary key" => ""));'), $arr)),
				implode(", ", array_map(create_function('$_', '$tmp = explode(" ", $_); return array_shift($tmp);'), array_filter($arr, create_function('$_', 'return mb_strstr($_, "primary key");'))))
			), array(",
					primary key()" => "")))));
			
			if (is_array($index))
				foreach ($index as $k => $v)
					$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('create index if not exists %s on %s(%s)', $k, $name, is_array($v) ? implode(", ", $v) : $v))));
			
			$this->registerTableByHandle($db, $name);
		}
	}
	
	/**
	 * @param string $name
	 * @param string $indexSuffix [optional]
	 */
	abstract function createFullTextTableIfNotExists(PDO $db, array $schema, $name, $indexSuffix = "Index");
	
	/**
	 * @param string $name
	 */
	function dropTable(PDO $db, $name)
	{
		$this->executeStatement($this->ensureStatement($db, $db->prepare('drop table ' . $name)));
		$this->unregisterTableByHandle($db, $name);
	}
	
	/**
	 * @param mixed $obj
	 * @param string $name
	 */
	function saveToTable(PDO $db, $obj, array $schema, $name)
	{
		$st = $this->ensureStatement($db, $db->prepare(sprintf
		('
			replace into %s
			(
				%s
			)
			values
			(
				:%s
			)',
			$name,
			implode(", ", array_keys($schema)),
			implode(", :", array_keys($schema))
		)));
		$this->bindValues($st, $obj, $schema);
		$this->executeStatement($st);
	}
	
	/**
	 * @param mixed $obj
	 */
	function bindValues(PDOStatement $st, $obj, array $schema)
	{
		foreach ($schema as $k => $v)
		{
			$type = explode(" ", $v, 2);
			$type = $type[0];
			
			if (!property_exists($obj, $k))
				continue;
			
			$value = $obj->$k;
			
			if (is_null($value))
				$type = PDO::PARAM_NULL;
			else if (strpos($type, "int") !== false)
				$type = PDO::PARAM_INT;
			else if ($type == "bit")
				$type = PDO::PARAM_BOOL;
			else
				$type = PDO::PARAM_STR;
			
			if ($type == PDO::PARAM_BOOL)
				if ($value)
					$st->bindValue(":" . $k, 1, PDO::PARAM_INT);
				else
					$st->bindValue(":" . $k, null, PDO::PARAM_NULL);
			else
				$st->bindValue(":" . $k, $value, $type);
		}
	}
}

class SQLiteDataStore extends DataStore
{
	private $directory;
	private $handleOpenCount = array();
	
	const MODULE_FTS3 = "fts3";
	const MODULE_FTS4 = "fts4";
	
	/**
	 * @param string $directory
	 */
	function __construct($directory = DATA_DIR)
	{
		App::precondition(extension_loaded("pdo_sqlite"), "PDO SQLite");
		
		$this->directory = $directory;
	}
	
	/**
	 * @param string $name
	 * @return PDO
	 */
	function open($name = "data")
	{
		if (!isset($this->handleOpenCount[$name]))
			$this->handleOpenCount[$name] = 0;
		
		$this->handleOpenCount[$name]++;
		
		if ($rt = $this->getHandleByName($name))
			return $rt;
		
		$db = new PDO(sprintf("sqlite:%s%s.sqlite", rtrim($this->directory, "/") . "/", $name), null, null);
		$this->registerHandle($db, $name);
		
		Util::executeStatement(Util::ensureStatement($db, $db->prepare('pragma recursive_triggers = true;')));
		
		if ($name == App::INDEX_DATABASE)
			SearchIndex::ensureTable($db);
		else
		{
			Meta::ensureTable($db);
			Board::ensureTable($db);
		}
		
		return $db;
	}
	
	/**
	 * @param bool $vacuum [optional]
	 */
	function close(PDO &$db, $vacuum = false)
	{
		$name = $this->getDatabaseNameByHandle($db);
		
		if (--$this->handleOpenCount[$name] > 0)
			return;
		
		unset($this->handleOpenCount[$name]);
		$this->unregisterHandle($db);
		
		if ($vacuum)
			$db->exec("vacuum");
		
		$db = null;
	}
	
	/**
	 * @return array|string
	 */
	function getTables(PDO $db)
	{
		$st = $this->ensureStatement($db, $db->prepare("select name from sqlite_master where type = 'table'"));
		$this->executeStatement($st, array());
		
		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
	}
	
	/**
	 * @param string $name
	 */
	function alterTable(PDO $db, array $schema, $name, array $index = null)
	{
		$tempName = "{$name}Temp";
		$this->createTableIfNotExists($db, $schema, $tempName, $index);
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('insert into %s select * from %s', $tempName, $name))));
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('drop table %s', $name))));
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('alter table %s rename to %s', $tempName, $name))));
	}
	
	/**
	 * @param string $name
	 * @param string $indexSuffix [optional]
	 */
	function createFullTextTableIfNotExists(PDO $db, array $schema, $name, $indexSuffix = "Index")
	{
		if (!$this->hasTable($db, $name))
		{
			$module = $this->supportedFullTextSearchModule();
			$arr = array_map(create_function('$x, $y', 'return "{$x} {$y}";'), array_keys($schema), array_values($schema));
			
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf
			("
				create virtual table %s using %s
				(
					%s
				)",
				$name,
				$module,
				implode(", ", array_filter(array_map(create_function('$_', 'return strtr($_, array(" fulltext" => ""));'), $arr), create_function('$_', 'return strpos($_, "rowid") === false && strpos($_, "docid") === false;')))
			))));
			
			$this->registerTableByHandle($db, $name);
		}
	}
	
	/**
	 * @return string
	 */
	function supportedFullTextSearchModule()
	{
		$db = new PDO("sqlite::memory:");
		$rt = null;
		
		foreach	(array(self::MODULE_FTS4, self::MODULE_FTS3) as $i)
			if ($this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('create virtual table temp using %s', $i))), array(), false))
			{
				$rt = $i;
				
				break;
			}
		
		$db = null;
		
		return $rt;
	}
}

class MySQLDataStore extends DataStore
{
	private $openCount = 0;	
	
	/**
	 * @var string
	 */
	private $host;
	/**
	 * @var int
	 */
	private $port;
	/**
	 * @var string
	 */
	private $databaseName;
	/**
	 * @var string
	 */
	private $unixSocket;
	/**
	 * @var string
	 */
	private $userName;
	/**
	 * @var string
	 */
	private $password;
	
	/**
	 * @param string $databaseName
	 * @param string $userName
	 * @param string $password
	 */
	function __construct($databaseName, $hostAndPortOrUnixSocket, $userName, $password)
	{
		App::precondition(extension_loaded("pdo_mysql"), "PDO MySQL");
		
		$this->databaseName = $databaseName;
		
		if (is_array($hostAndPortOrUnixSocket))
			list($this->host, $this->port) = $hostAndPortOrUnixSocket;
		else
			$this->unixSocket = $hostAndPortOrUnixSocket;
		
		$this->userName = $userName;
		$this->password = $password;
	}
	
	/**
	 * @param string $name
	 * @return PDO
	 */
	function open($name = "data")
	{
		$this->openCount++;
		
		if ($name == App::INDEX_DATABASE)
			if ($rt = $this->getHandleByName("data"))
				return $rt;
			else
			{
				$this->openCount--;
				
				return $this->open("data");
			}
		
		$db = new PDO
		(
			sprintf("mysql:%s;dbname=%s;charset=utf8", is_null($this->unixSocket) ? "host={$this->host};port={$this->port}" : "unixsocket={$this->unixSocket}", $this->databaseName),
			$this->userName,
			$this->password,
			array
			(
				PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8",
			)
		);
		$this->registerHandle($db, $name);
		
		Meta::ensureTable($db);
		Board::ensureTable($db);
		SearchIndex::ensureTable($db);
		
		return $db;
	}
	
	/**
	 * @param bool $vacuum [optional]
	 */
	function close(PDO &$db, $vacuum = false)
	{
		if (--$this->openCount > 0)
			return;
		
		$this->unregisterHandle($db);
		$db = null;
	}
	
	/**
	 * @return array|string
	 */
	function getTables(PDO $db)
	{
		$st = $this->ensureStatement($db, $db->prepare("show tables"));
		$this->executeStatement($st, array());
		
		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
	}
	
	/**
	 * @param string $name
	 */
	function createTableIfNotExists(PDO $db, array $schema, $name, array $index = null)
	{
		if (!$this->hasTable($db, $name))
		{
			$arr = array_map(create_function('$x, $y', 'return "{$x} {$y}";'), array_keys($schema), array_values($schema));

			$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf
			("
				create table if not exists %s
				(
					%s,
					primary key(%s)%s
				)
				default character set utf8 engine InnoDB",
				$name,
				implode(", ", array_map(create_function('$_', 'return strtr($_, array(" primary key" => ""));'), $arr)),
				implode(", ", array_map(create_function('$_', '$tmp = explode(" ", $_); return array_shift($tmp);'), array_filter($arr, create_function('$_', 'return mb_strstr($_, "primary key");')))),
				is_array($index) ? ", key " . implode(", key ", array_map(create_function('$x, $y', 'return "{$x}(" . (is_array($y) ? implode(", ", $y) : $y) . ")";'), array_keys($index), array_values($index))) : ""
			), array(",
					primary key()" => "")))));
			
			$this->registerTableByHandle($db, $name);
		}
	}
	
	/**
	 * @param string $name
	 * @param string $indexSuffix [optional]
	 */
	function createFullTextTableIfNotExists(PDO $db, array $schema, $name, $indexSuffix = "Index")
	{
		if (!$this->hasTable($db, $name))
		{
			$columns = array_map(create_function('$x, $y', 'return "{$x} " . strtr($y, array(" primary key" => "", " fulltext" => ""));'), array_keys($schema), array_values($schema));
			$primaryKeys = array_keys(array_filter($schema, create_function('$_', 'return strpos($_, "primary key") !== false;')));
			$fullTextIndices = array_keys(array_filter($schema, create_function('$_', 'return strpos($_, "fulltext") !== false;')));
	
			$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf
			("
				create table if not exists %s
				(
					%s,
					primary key(%s)%s
				)
				default character set utf8 collate utf8_unicode_ci engine MyISAM",
				$name,
				implode(", ", $columns),
				implode(", ", $primaryKeys),
				", fulltext index " . implode(", fulltext index ", array_map(create_function('$x, $y', 'return "{$x}{$y}({$x})";'), $fullTextIndices, array_fill(0, count($fullTextIndices), $indexSuffix)))
			), array(",
					primary key()" => "")))));
			
			$this->registerTableByHandle($db, $name);
		}
	}
	
	function attachFullTextIndex(PDO $db, array $schema, $name, $indexSuffix = "Index")
	{
		$fullTextIndices = array_keys(array_filter($schema, create_function('$_', 'return strpos($_, "fulltext") !== false;')));
		
		foreach ($fullTextIndices as $i)
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf
			("
				create fulltext index %s on %s(%s)",
				$i . $indexSuffix,
				$name,
				$i
			))));
	}

	function detachFullTextIndex(PDO $db, array $schema, $name, $indexSuffix = "Index")
	{
		$fullTextIndices = array_keys(array_filter($schema, create_function('$_', 'return strpos($_, "fulltext") !== false;')));
		
		foreach ($fullTextIndices as $i)
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf
			("
				drop index %s on %s",
				$i . $indexSuffix,
				$name
			))), array(), false);
	}
}
?>