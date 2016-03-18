<?php
class SessionStore
{
	static $sessionStoreSchema = array
	(
		"name" => "varchar(255) primary key not null",
		"id" => "varchar(255) primary key not null",
		"lastUpdate" => "bigint not null",
		"data" => "text",
	);
	
	/**
	 * @var SessionStore
	 */
	static $instance;
	private $db;
	private $sessionName;
	
	static function useSessionStore()
	{
		self::$instance = new SessionStore();
		self::$instance->apply();
	}
	
	function open($savePath, $sessionName)
	{
		$this->db = App::openDB();
		$this->sessionName = $sessionName;
		
		Util::createTableIfNotExists($this->db, self::$sessionStoreSchema, App::SESSION_STORE_TABLE, array
		(
			App::SESSION_STORE_TABLE . "LastUpdateIndex" => array("lastUpdate")
		));
		
		return true;
	}
	
	function close()
	{
		App::closeDB($this->db);
		
		return true;
	}
	
	function read($sessionId)
	{
		$st = Util::ensureStatement($this->db, $this->db->prepare(sprintf
		('
			select * from %s
			where name = ? and id = ?',
			App::SESSION_STORE_TABLE
		)));
		Util::executeStatement($st, array($this->sessionName, $sessionId));
		$rt = $st->fetchAll();
		
		if (count($rt))
			return $rt[0]["data"];
		else
			return "";
	}
	
	function write($sessionId, $data)
	{
		Util::executeStatement(Util::ensureStatement($this->db, $this->db->prepare(sprintf
		('
			replace into %s(name, id, lastUpdate, data) values(?, ?, ?, ?)',
			App::SESSION_STORE_TABLE
		))), array
		(
			$this->sessionName,
			$sessionId,
			time(),
			$data
		));
		
		return true;
	}
	
	function destroy($sessionId)
	{
		Util::executeStatement(Util::ensureStatement($this->db, $this->db->prepare(sprintf
		('
			delete from %s where name = ? and id = ?',
			App::SESSION_STORE_TABLE
		))), array
		(
			$this->sessionName,
			$sessionId
		), false);
		
		return true;
	}
	
	function gc($lifetime)
	{
		Util::executeStatement(Util::ensureStatement($this->db, $this->db->prepare(sprintf
		('
			delete from %s where lastUpdate <= %d',
			App::SESSION_STORE_TABLE,
			time() - $lifetime
		))), null, false);
		
		return true;
	}
	
	function apply()
	{
		ini_set("session.serialize_handler", "php");
		
		session_set_save_handler
		(
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
		);
		register_shutdown_function('session_write_close');
	}
}
?>