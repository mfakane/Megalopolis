<?php

namespace Megalopolis;

use \PDO;

class SessionStore implements \SessionHandlerInterface
{
	static array $sessionStoreSchema = array(
		"name" => "varchar(255) primary key not null",
		"id" => "varchar(255) primary key not null",
		"lastUpdate" => "bigint not null",
		"data" => "text",
	);

	static SessionStore $instance;
	private ?DataStoreHandle $dh = null;
	private ?string $sessionName = null;

	static function useSessionStore(): void
	{
		self::$instance = new self();
		self::$instance->apply();
	}

	#[\Override]
	function open(string $path, string $name): bool
	{
		$this->dh = App::openDB();
		$this->sessionName = $name;

		$this->dh->execute(fn($db) => Util::createTableIfNotExists($db, self::$sessionStoreSchema, App::SESSION_STORE_TABLE, array(
			App::SESSION_STORE_TABLE . "LastUpdateIndex" => array("lastUpdate")
		)));

		return true;
	}

	#[\Override]
	function close(): bool
	{
		if ($this->dh)
			$this->dh->close();

		$this->dh = null;

		return true;
	}

	#[\Override]
	function read(string $id): string
	{
		if (!$this->dh)
			return "";

		$rt = $this->dh->execute(function ($db) use ($id) {
			$st = Util::ensureStatement($db, $db->prepare(sprintf(
				'
			select * from %s
			where name = ? and id = ?',
				App::SESSION_STORE_TABLE
			)));
			Util::executeStatement($st, array($this->sessionName, $id));
			return $st?->fetchAll() ?? [];
		});


		if (count($rt))
			return $rt[0]["data"];
		else
			return "";
	}

	#[\Override]
	function write(string $id, string $data): bool
	{
		if (!$this->dh)
			return false;

		$this->dh->execute(fn($db) => Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf(
			'
			replace into %s(name, id, lastUpdate, data) values(?, ?, ?, ?)',
			App::SESSION_STORE_TABLE
		))), array(
			$this->sessionName,
			$id,
			time(),
			$data
		)));

		return true;
	}

	#[\Override]
	function destroy(string $id): bool
	{
		if (!$this->dh)
			return false;

		$this->dh->execute(fn($db) => Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf(
			'
			delete from %s where name = ? and id = ?',
			App::SESSION_STORE_TABLE
		))), array(
			$this->sessionName,
			$id
		), false));

		return true;
	}

	#[\Override]
	function gc(int $max_lifetime): false|int
	{
		if (!$this->dh)
			return false;

		$rt = $this->dh->execute(function ($db) use ($max_lifetime) {
			$st = Util::ensureStatement($db, $db->prepare(sprintf(
				'delete from %s where lastUpdate <= %d',
				App::SESSION_STORE_TABLE,
				time() - $max_lifetime
			)));
			Util::executeStatement($st, null, false);

			return $st?->rowCount() ?? 0;
		});

		return $rt;
	}

	function apply(): void
	{
		ini_set("session.serialize_handler", "php");

		session_set_save_handler($this);
		register_shutdown_function('session_write_close');
	}
}
