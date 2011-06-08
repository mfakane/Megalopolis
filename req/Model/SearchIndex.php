<?php
class SearchIndex
{
	static $searchIndexSchema = array
	(
		"id" => "integer",
		"type" => "text",
		"word" => "text"
	);
	static $endchars = "　｛｝「」【】（）〈〉≪≫『』〔〕［］＜＞、。・…／＆！”＃＄％’ー＝＾～｜￥＋＊‘＠：；？＿";
	
	static function register(PDO $idb, Thread $thread)
	{
		self::unregister($idb, $thread->id);
		$words = array_filter(array
		(
			"title" => self::getWords($thread->entry->title),
			"name" => self::getWords($thread->entry->name),
			"summary" => self::getWords($thread->entry->summary),
			"body" => Configuration::$instance->registerBodyToSearchIndex ? self::getWords($thread->body) : null,
			"afterword" => self::getWords($thread->afterword),
			"tag" => call_user_func_array(array("SearchIndex", "getWords"), $thread->entry->tags)
		));
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			insert into %s(id, type, word)
			values
			(
				%d,
				?,
				?
			);',
			App::INDEX_TABLE,
			$thread->id
		)));

		foreach ($words as $k => $v)
			foreach ($v as $i)
				$st->execute(array($k, $i));
	}
	
	/**
	 * @param int $id
	 */
	static function unregister(PDO $idb, $id)
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			delete from %s
			where id = %d',
			App::INDEX_TABLE,
			$id
		)));
		Util::executeStatement($st);
	}
	
	static function search(PDO $idb, array $query, $type = null)
	{
		if (!$query)
			return array();
		
		if (!($query = call_user_func_array(array("SearchIndex", "getWords"), $query)))
			return array();
		
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select id, word from %s
			where word in (%s) %s',
			App::INDEX_TABLE,
			implode(", ", array_fill(0, count($query), "?")),
			$type ? "and type = '{$type}'" : null
		)));
		Util::executeStatement($st, $query);
		$arr = $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
		$rt = array();
		
		foreach ($arr as $k => $v)
			if (count(array_intersect($v, $query)) == count($query))
				$rt[] = $k;
		
		return $rt;
	}
	
	static function getWords()
	{
		$rt = array();
		$gram = 2;
		
		foreach (func_get_args() as $i)
		{
			$i = mb_strtolower($i);
			$l = mb_strlen($i);
			$arr = array();
			$m = $i;
			
			if ($l < $gram)
			{
				$rt[] = $i;
				
				continue;
			}
			
			foreach (range(1, $l) as $k)
			{
				$arr[] = mb_substr($m, 0, 1);
				$m = mb_substr($m, 1);
			}
			
			foreach (range(0, $l - 1) as $k)
			{
				$str = "";
				
				foreach (range($k, $k + $gram - 1) as $j)
					if ($j < $l)
					{
						$v = $arr[$j];
						
						if (self::actAsEnd($v))
						{
							$str = "";
							
							break;
						}
						else
							$str .= $v;
					}
					else
					{
						$str = "";
							
						break;
					}
				
				if (!Util::isEmpty($str))
					$rt[] = $str;
			}
		}
		
		return array_filter(array_unique($rt), "strlen");
	}
	
	private static function actAsEnd($v)
	{
		$ord = ord($v);
		
		return $ord >= 0 && $ord <= 47
			|| $ord >= 58 && $ord <= 64
			|| $ord >= 91 && $ord <= 96
			|| $ord >= 123 && $ord <= 127
			|| mb_strpos(self::$endchars, $v) !== false;
	}
	
	static function ensureTable(PDO $idb)
	{
		Util::createTableIfNotExists($idb, self::$searchIndexSchema, App::INDEX_TABLE);
	}
}
?>