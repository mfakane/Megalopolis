<?php
class Meta
{
	const DATA_VERSION = "dataVersion";
	const DATA_VERSION_VALUE_LATEST = "1";
	
	static $meta = null;
	static $metaTableSchema = array
	(
		"name" => "varchar(255) primary key not null",
		"value" => "varchar(255)"
	);
	
	/**
	 * @param string $name
	 * @param string $defaultValue
	 * @return string
	 */
	static function get(PDO $db, $name, $defaultValue = null)
	{
		if (self::$meta == null)
		{
			self::$meta = array();
			
			foreach (Util::ensureStatement($db, $db->query("select * from " . App::META_TABLE))->fetchAll() as $i)
				self::$meta[$i["name"]] = $i["value"];
		}
		
		return isset(self::$meta[$name]) ? self::$meta[$name] : $defaultValue;
	}
	
	/**
	 * @param string $name
	 */
	static function set(PDO $db, $name, $value)
	{
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
	
	static function ensureTable(PDO $db)
	{
		if (!Util::hasTable($db, App::META_TABLE))
		{
			Util::createTableIfNotExists($db, self::$metaTableSchema, App::META_TABLE);
			self::set($db, self::DATA_VERSION, self::DATA_VERSION_VALUE_LATEST);
		}
	}
}
?>