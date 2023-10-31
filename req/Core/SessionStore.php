<?php
class SessionStore
{
	static array $sessionStoreSchema = array
	(
		"name" => "varchar(255) primary key not null",
		"id" => "varchar(255) primary key not null",
		"lastUpdate" => "bigint not null",
		"data" => "text",
	);
	
	static SessionStore $instance;
	private ?PDO $db = null;
	private ?string $sessionName = null;
	
	static function useSessionStore(): void
	{
		self::$instance = new SessionStore();
		self::$instance->apply();
	}
	
	function open(string $savePath, string $sessionName): bool
	{
		$this->db = App::openDB();
		$this->sessionName = $sessionName;
		
		Util::createTableIfNotExists($this->db, self::$sessionStoreSchema, App::SESSION_STORE_TABLE, array
		(
			App::SESSION_STORE_TABLE . "LastUpdateIndex" => array("lastUpdate")
		));
		
		return true;
	}
	
	function close(): bool
	{
		if ($this->db)
			App::closeDB($this->db);
		
		return true;
	}
	
	function read(string $sessionId): string
	{
		if (!$this->db)
			return "";

		$st = Util::ensureStatement($this->db, $this->db->prepare(sprintf
		('
			select * from %s
			where name = ? and id = ?',
			App::SESSION_STORE_TABLE
		)));
		Util::executeStatement($st, array($this->sessionName, $sessionId));
		$rt = $st?->fetchAll();
		
		if (isset($rt) && count($rt))
			return $rt[0]["data"];
		else
			return "";
	}
	
	function write(string $sessionId, string $data): bool
	{
		if (!$this->db)
			return false;

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
	
	function destroy(string $sessionId): bool
	{
		if (!$this->db)
			return false;

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
	
	function gc(string $lifetime): bool
	{
		if (!$this->db)
			return false;

		Util::executeStatement(Util::ensureStatement($this->db, $this->db->prepare(sprintf
		('
			delete from %s where lastUpdate <= %d',
			App::SESSION_STORE_TABLE,
			time() - (int)$lifetime
		))), null, false);
		
		return true;
	}
	
	function apply(): void
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
