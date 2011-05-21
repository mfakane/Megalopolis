<?php
class Meta
{
	const DATA_VERSION = "dataVersion";
	const DATA_VERSION_VALUE_LATEST = "1";
	
	static $meta = null;
	static $metaTableSchema = array
	(
		"name" => "text primary key not null",
		"value" => "text"
	);
	
	/**
	 * @param string $name
	 * @return string
	 */
	static function get(PDO $db, $name)
	{
		if ($meta == null)
		{
			$meta = array();
			
			foreach (Util::ensureStatement($db, $db->query("select * from" . self::META_TABLE))->fetchAll() as $i)
				$meta[$i["name"]] = $i["value"];
		}
		
		return $meta[$name];
	}
	
	/**
	 * @param string $name
	 */
	static function set(PDO $db, $name, $value)
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			insert or replace into %s
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
		
		$meta[$name] = $value;
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