<?php
namespace Megalopolis;

use \PDO;

class Meta
{
	const DATA_VERSION = "dataVersion";
	const DATA_VERSION_VALUE_LATEST = "1";
	
	static ?array $meta = null;
	static array $metaTableSchema = array
	(
		"name" => "varchar(255) primary key not null",
		"value" => "varchar(255)"
	);
	
	private static function query(PDO $db, array $names = array()): void
	{
		$st = Util::ensureStatement($db, $db->prepare("select * from " . App::META_TABLE . ($names ? " where name in (" . implode(", ", array_fill(0, count($names), "?")) . ")" : "")));
		Util::executeStatement($st, $names);
		
		if ($st)
			foreach ($st->fetchAll() as $i)
				self::$meta[$i["name"]] = $i["value"];
	}
	
	static function get(PDO $db, string $name, ?string $defaultValue = null): ?string
	{
		if (!isset(self::$meta[$name]))
			self::query($db, array($name));
		
		return isset(self::$meta[$name]) ? self::$meta[$name] : $defaultValue;
	}
	
	static function set(PDO $db, string $name, ?string $value): void
	{
		if (isset(self::$meta[$name]) && self::$meta[$name] === $value)
			return;
		
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			replace into %s
			(
				name,
				value
			)
			values
			(
				?,
				?
			)',
			App::META_TABLE
		))),
		array
		(
			$name,
			$value
		));
		
		self::$meta[$name] = $value;
	}
	
	static function ensureTable(PDO $db): void
	{
		if (!Util::hasTable($db, App::META_TABLE))
		{
			$db->beginTransaction();
			Util::createTableIfNotExists($db, self::$metaTableSchema, App::META_TABLE);
			self::set($db, self::DATA_VERSION, self::DATA_VERSION_VALUE_LATEST);
			$db->commit();
		}
		
		self::query($db, array
		(
			self::DATA_VERSION,
			App::SUBJECT_TABLE,
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			App::THREAD_TAG_TABLE,
			App::THREAD_TABLE,
			App::THREAD_STYLE_TABLE,
			App::COMMENT_TABLE,
			App::EVALUATION_TABLE,
			App::SESSION_STORE_TABLE,
		));
	}
}
?>
