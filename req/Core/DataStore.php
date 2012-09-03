<?php
abstract class DataStore
{
	/**
	 * @param string $database
	 * @param bool $beginTransaction [optional]
	 * @return PDO
	 */
	abstract function open($database = "data", $beginTransaction = true);
	
	/**
	 * @param bool $vacuum [optional]
	 * @param bool $commitTransaction [optional]
	 */
	abstract function close(PDO &$db, $vacuum = false, $commitTransaction = true);
	
	/**
	 * @param string $name
	 */
	abstract function hasTable(PDO $db, $name);
	
	/**
	 * @param PDOStatement $st
	 * @return PDOStatement
	 */
	function ensureStatement(PDO $db, $st)
	{
		if ($st)
			return $st;
		else
			throw new ApplicationException(array_pop($db->errorInfo()));
	}
	
	/**
	 * @param bool $throw [optional]
	 * @return bool
	 */
	function executeStatement(PDOStatement $st, array $params = null, $throw = true)
	{
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
			implode(", ", array_map(create_function('$_', 'return array_shift(explode(" ", $_));'), array_filter($arr, create_function('$_', 'return mb_strstr($_, "primary key");'))))
		), array(",
				primary key()" => "")))));
		
		if (is_array($index))
			foreach ($index as $k => $v)
				$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('create index if not exists %s on %s(%s)', $k, $name, is_array($v) ? implode(", ", $v) : $v))));
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
			
			$st->bindValue(":" . $k, $value, $type);
		}
	}
}

class SQLiteDataStore extends DataStore
{
	private $directory;
	
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
	 * @param bool $beginTransaction [optional]
	 * @return PDO
	 */
	function open($name = "data", $beginTransaction = true)
	{
		$db = new PDO(sprintf("sqlite:%s%s.sqlite", rtrim($this->directory, "/") . "/", $name));
		
		if ($beginTransaction)
			$db->beginTransaction();
		
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
	 * @param bool $commitTransaction [optional]
	 */
	function close(PDO &$db, $vacuum = false, $commitTransaction = true)
	{
		if ($commitTransaction)
			$db->commit();
		
		if ($vacuum)
			$db->exec("vacuum");
		
		$db = null;
	}
	
	/**
	 * @param string $name
	 */
	function hasTable(PDO $db, $name)
	{
		$st = $this->ensureStatement($db, $db->prepare("select * from sqlite_master where type = 'table' and name = ?"));
		$this->executeStatement($st, array($name));
		
		return count($st->fetchAll()) > 0;
	}
}

class MySQLDataStore extends DataStore
{
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
	 * @param bool $beginTransaction [optional]
	 * @return PDO
	 */
	function open($name = "data", $beginTransaction = true)
	{
		$db = new PDO
		(
			sprintf("mysql:%s;dbname=%s;charset=utf8", is_null($this->unixSocket) ? "host={$this->host};port={$this->port}" : "unixsocket={$this->unixSocket}", $this->databaseName),
			$this->userName,
			$this->password,
			array(PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8")
		);
		
		if ($beginTransaction)
			$db->beginTransaction();
		
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
	 * @param bool $commitTransaction [optional]
	 */
	function close(PDO &$db, $vacuum = false, $commitTransaction = true)
	{
		if ($commitTransaction)
			$db->commit();
		
		$db = null;
	}
	
	/**
	 * @param string $name
	 */
	function hasTable(PDO $db, $name)
	{
		$st = $this->ensureStatement($db, $db->prepare("show tables like ?"));
		$this->executeStatement($st, array($name));
		
		return count($st->fetchAll()) > 0;
	}
	
	/**
	 * @param string $name
	 */
	function createTableIfNotExists(PDO $db, array $schema, $name, array $index = null)
	{
		$arr = array_map(create_function('$x, $y', 'return "{$x} {$y}";'), array_keys($schema), array_values($schema));

		$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf
		("
			create table if not exists %s
			(
				%s,
				primary key(%s)%s
			)
			default character set utf8",
			$name,
			implode(", ", array_map(create_function('$_', 'return strtr($_, array(" primary key" => ""));'), $arr)),
			implode(", ", array_map(create_function('$_', 'return array_shift(explode(" ", $_));'), array_filter($arr, create_function('$_', 'return mb_strstr($_, "primary key");')))),
			is_array($index) ? ", key " . implode(", key ", array_map(create_function('$x, $y', 'return "{$x}(" . (is_array($y) ? implode(", ", $y) : $y) . ")";'), array_keys($index), array_values($index))) : ""
		), array(",
				primary key()" => "")))));
	}
}
?>