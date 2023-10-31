<?php
abstract class DataStore
{
	/** @var array<string, PDO> */
	protected array $handles = array();
	/** @var string[][] */
	protected array $tableNames = array();
	
	protected function registerHandle(PDO &$db, string $name): void
	{
		$this->handles[$name] = &$db;
	}
	
	protected function unregisterHandle(PDO &$db): void
	{
		unset($this->handles[$this->getDatabaseNameByHandle($db)]);
	}
	
	protected function getDatabaseNameByHandle(PDO &$db): string|false
	{
		return array_search($db, $this->handles, true);
	}
	
	protected function getHandleByName(string $name): ?PDO
	{
		return isset($this->handles[$name]) ? $this->handles[$name] : null;
	}
	
	protected function registerTableByHandle(PDO &$db, string $name): void
	{
		$this->tableNames[$this->getDatabaseNameByHandle($db)][] = strtolower($name);
	}
	
	protected function unregisterTableByHandle(PDO &$db, string $name): void
	{
		$arr = &$this->tableNames[$this->getDatabaseNameByHandle($db)];
		
		unset($arr[array_search($name, $arr)]);
	}
	
	abstract function open(string $database = "data"): PDO;
	
	abstract function close(PDO &$db, bool $vacuum = false): void;
	
	/**
	 * @return string[]
	 */
	abstract function getTables(PDO $db);
	
	/**
	 * @param string $name
	 */
	function hasTable(PDO $db, $name): bool
	{
		$dbname = $this->getDatabaseNameByHandle($db);
		
		if (!isset($this->tableNames[$dbname]))
			$this->tableNames[$dbname] = array_map("strtolower", $this->getTables($db));
		
		return in_array(strtolower($name), $this->tableNames[$dbname]);
	}
	
	function ensureStatement(PDO $db, ?PDOStatement $st, bool $throw = true): ?PDOStatement
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
	
	function executeStatement(?PDOStatement $st, array $params = null, bool $throw = true): bool
	{
		if (!$st) return false;

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
	
	function createTableIfNotExists(PDO $db, array $schema, string $name, array $index = null): bool
	{
		if (!$this->hasTable($db, $name))
		{
			$arr = array_map(fn($x, $y) => "{$x} {$y}", array_keys($schema), array_values($schema));

			$st =$this->ensureStatement($db, $db->prepare(strtr(sprintf
			("
				create table if not exists %s
				(
					%s,
					primary key(%s)
				)",
				$name,
				implode(", ", array_map(fn($_) => strtr($_, array(" primary key" => "")), $arr)),
				implode(", ", array_map(fn($_) => explode(" ", $_)[0], array_filter($arr, fn($_) => mb_strstr($_, "primary key"))))
			), array(",
					primary key()" => ""))));

			$this->executeStatement($st);
			
			if (is_array($index))
				foreach ($index as $k => $v)
				{
					$st = $this->ensureStatement($db, $db->prepare(sprintf('create index if not exists %s on %s(%s)', $k, $name, is_array($v) ? implode(", ", $v) : $v)));
					$this->executeStatement($st);
				}
			
			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}
	
	abstract function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool;
	
	function dropTable(PDO $db, string $name): void
	{
		$this->executeStatement($this->ensureStatement($db, $db->prepare('drop table ' . $name)));
		$this->unregisterTableByHandle($db, $name);
	}
	
	function saveToTable(PDO $db, mixed $obj, array $schema, string $name): void
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
		if (!$st) return;
		$this->bindValues($st, $obj, $schema);
		$this->executeStatement($st);
	}
	
	function bindValues(PDOStatement $st, mixed $obj, array $schema): void
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
			else if ($type === "bit")
				$type = PDO::PARAM_BOOL;
			else
				$type = PDO::PARAM_STR;
			
			if ($type === PDO::PARAM_BOOL)
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
	private string $directory;

	/** @var array<string, int> */
	private $handleOpenCount = array();
	
	const MODULE_FTS3 = "fts3";
	const MODULE_FTS4 = "fts4";
	
	function __construct(string $directory = DATA_DIR)
	{
		App::precondition(extension_loaded("pdo_sqlite"), "PDO SQLite");
		
		$this->directory = $directory;
	}
	
	function open(string $database = "data"): PDO
	{
		if (!isset($this->handleOpenCount[$database]))
			$this->handleOpenCount[$database] = 0;
		
		$this->handleOpenCount[$database]++;
		
		if ($rt = $this->getHandleByName($database))
			return $rt;
		
		$db = new PDO(sprintf("sqlite:%s%s.sqlite", rtrim($this->directory, "/") . "/", $database), null, null);
		$this->registerHandle($db, $database);
		
		Util::executeStatement(Util::ensureStatement($db, $db->prepare('pragma recursive_triggers = true;')));
		
		if ($database == App::INDEX_DATABASE)
			SearchIndex::ensureTable($db);
		else
		{
			Meta::ensureTable($db);
			Board::ensureTable($db);
		}
		
		return $db;
	}
	
	function close(PDO &$db, bool $vacuum = false): void
	{
		$name = $this->getDatabaseNameByHandle($db);
		
		if (!$name || --$this->handleOpenCount[$name] > 0)
			return;
		
		unset($this->handleOpenCount[$name]);
		$this->unregisterHandle($db);
		
		if ($vacuum)
			$db->exec("vacuum");
	}
	
	/**
	 * @return string[]
	 */
	function getTables(PDO $db)
	{
		$st = $this->ensureStatement($db, $db->prepare("select name from sqlite_master where type = 'table'"));
		if (!$st) return array();

		$this->executeStatement($st, array());
		
		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
	}
	
	function alterTable(PDO $db, array $schema, string $name, array $index = null): void
	{
		$tempName = "{$name}Temp";
		$this->createTableIfNotExists($db, $schema, $tempName, $index);
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('insert into %s select * from %s', $tempName, $name))));
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('drop table %s', $name))));
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('alter table %s rename to %s', $tempName, $name))));
	}

	function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool
	{
		if (!$this->hasTable($db, $name))
		{
			$module = $this->supportedFullTextSearchModule() ?? "";
			$arr = array_map(fn($x, $y) => "{$x} {$y}", array_keys($schema), array_values($schema));
			
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf
			("
				create virtual table %s using %s
				(
					%s
				)",
				$name,
				$module,
				implode(", ", array_filter(array_map(fn($_) => strtr($_, array(" fulltext" => "")), $arr), fn($_) => strpos($_, "rowid") === false && strpos($_, "docid") === false))
			))));
			
			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}
	
	function supportedFullTextSearchModule(): ?string
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
	private int $openCount = 0;
	private ?string $host = null;
	private ?int $port = null;
	private string $databaseName;
	private ?string $unixSocket = null;
	private string $userName;
	private string $password;

	/**
	 * @param string|(string|int)[] $hostAndPortOrUnixSocket
	 */
	function __construct(string $databaseName, $hostAndPortOrUnixSocket, string $userName, string $password)
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

	function open(string $database = "data"): PDO
	{
		$this->openCount++;
		
		if ($database == App::INDEX_DATABASE)
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
		$this->registerHandle($db, $database);
		
		Meta::ensureTable($db);
		Board::ensureTable($db);
		SearchIndex::ensureTable($db);
		
		return $db;
	}
	
	function close(PDO &$db, bool $vacuum = false): void
	{
		if (--$this->openCount > 0)
			return;
		
		$this->unregisterHandle($db);
	}
	
	/**
	 * @return string[]
	 */
	function getTables(PDO $db)
	{
		$st = $this->ensureStatement($db, $db->prepare("show tables"));
		if (!$st) return array();
		
		$this->executeStatement($st, array());
		
		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
	}
	
	function createTableIfNotExists(PDO $db, array $schema, string $name, array $index = null): bool
	{
		if (!$this->hasTable($db, $name))
		{
			$arr = array_map(fn($x, $y) => "{$x} {$y}", array_keys($schema), array_values($schema));

			$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf
			("
				create table if not exists %s
				(
					%s,
					primary key(%s)%s
				)
				default character set utf8 engine InnoDB",
				$name,
				implode(", ", array_map(fn($_) => strtr($_, array(" primary key" => "")), $arr)),
				implode(", ", array_map(fn($_) => explode(" ", $_)[0], array_filter($arr, fn($_) => mb_strstr($_, "primary key")))),
				is_array($index) ? ", key " . implode(", key ", array_map(fn($x, $y) => "{$x}(" . (is_array($y) ? implode(", ", $y) : $y) . ")", array_keys($index), array_values($index))) : ""
			), array(",
					primary key()" => "")))));
			
			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}
	
	function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool
	{
		if (!$this->hasTable($db, $name))
		{
			$columns = array_map(fn($x, $y) => "{$x} " . strtr($y, array(" primary key" => "", " fulltext" => "")), array_keys($schema), array_values($schema));
			$primaryKeys = array_keys(array_filter($schema, fn($_) => strpos($_, "primary key") !== false));
			$fullTextIndices = array_keys(array_filter($schema, fn($_) => strpos($_, "fulltext") !== false));
	
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
				", fulltext index " . implode(", fulltext index ", array_map(fn($x, $y) => "{$x}{$y}({$x})", $fullTextIndices, array_fill(0, count($fullTextIndices), $indexSuffix)))
			), array(",
					primary key()" => "")))));
			
			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}
	
	function attachFullTextIndex(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): void
	{
		$fullTextIndices = array_keys(array_filter($schema, fn($_) => strpos($_, "fulltext") !== false));
		
		foreach ($fullTextIndices as $i)
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf
			("
				create fulltext index %s on %s(%s)",
				$i . $indexSuffix,
				$name,
				$i
			))));
	}

	function detachFullTextIndex(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): void
	{
		$fullTextIndices = array_keys(array_filter($schema, fn($_) => strpos($_, "fulltext") !== false));
		
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
