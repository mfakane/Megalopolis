<?php
namespace Megalopolis;

use \PDO;

class SQLiteSearchIndex extends SearchIndex
{
	const string INDEX_TABLE = "searchIndex2";
	static array $searchIndexSchema = array
	(
		"docid" => "bigint primary key not null",
		"title" => "varchar(2048) fulltext",
		"name" => "varchar(2048) fulltext",
		"summary" => "mediumtext fulltext",
		"body" => "mediumtext fulltext",
		"afterword" => "mediumtext fulltext",
		"tag" => "varchar(2048) fulltext"
	);
	
	#[\Override]
	function registerThread(PDO $idb, Thread $thread, bool $removeExisting): void
	{
		if ($thread->id === 0)
			return;

		if ($removeExisting)
			self::unregister($idb, $thread->id);
		
		$words = array_filter(array
		(
			"title" => $this->getWords([$this->phraseWords(), $thread->entry->title]),
			"name" => $this->getWords([$this->phraseWords(), $thread->entry->name]),
			"summary" => $this->getWords([$this->phraseWords(), $thread->entry->summary]),
			"body" => Configuration::$instance->registerBodyToSearchIndex ? $this->getWords([$this->phraseWords(), $thread->body]) : null,
			"afterword" => $this->getWords([$this->phraseWords(), $thread->afterword]),
			"tag" => $this->getWords(array_merge([$this->phraseWords()], $thread->entry->tags))
		));
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			insert into %s(docid, %s)
			values
			(
				%d,
				%s
			);',
			self::INDEX_TABLE,
			implode(", ", array_keys($words)),
			$thread->id,
			implode(", ", array_map(function($_) { return ":{$_}"; }, array_keys($words)))
		)));
		Util::executeStatement($st, array_map(function($_) { return implode(" ", $_); }, $words));
	}
	
	#[\Override]
	function unregisterThread(PDO $idb, array $ids): void
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			delete from %s
			where docid in (%s)',
			self::INDEX_TABLE,
			implode(", ", array_map('intval', $ids))
		)));
		Util::executeStatement($st);
	}

	#[\Override]
	function searchThread(PDO $idb, array $query, ?array $type = null, ?array $ids = null): array
	{
		if (!$query)
			return array();
		
		if (!($matchQuery = $this->buildMatchQuery($query, self::hasEnhancedQuerySyntax($idb))))
			return array();
		
		$targetColumns = $type == null ? array_keys(self::$searchIndexSchema) : $type;
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select docid from
			(' . implode(" union ", array_map(function($_) { return "select docid from %2\$s where {$_} match ?"; }, $targetColumns)) . ') %1$s',
			is_array($ids) ? "where docid in (" . ($ids ? implode(", ", $ids) : -1) . ")" : "",
			self::INDEX_TABLE
		)));
		
		Util::executeStatement($st, array_fill(0, count($targetColumns), $matchQuery));
		
		return $st?->fetchAll(PDO::FETCH_COLUMN, 0) ?? array();
	}
	
	/**
	 * 検索語から MATCH 用の条件を作る
	 * @param string[] $query
	 */
	function buildMatchQuery(array $query, bool $enhancedSyntax): string
	{
		$include = array();
		$exclude = array();
		
		foreach ($query as $i)
		{
			$isExclude = strpos($i, "-") === 0;
			
			if ($isExclude)
				$i = substr($i, 1);
			
			// 索引は記号などで区切った部分ごとに n-gram にしているため、部分ごとに条件にする
			$terms = array();
			$allTerms = array();
			
			foreach (self::splitSegments($i) as $segment)
			{
				$words = $this->getWords([$this->phraseWords() + array("endOnIncompletedGram" => true, "noIncompletedGram" => mb_strlen($segment) >= $this->gramLength), $segment]);
				
				if (!$words)
					continue;
				else if (mb_strlen($words[0]) == $this->gramLength)
				{
					$terms[] = '"' . implode(" ", $words) . '"';
					array_push($allTerms, ...$words);
				}
				else
					$allTerms[] = $terms[] = "{$words[0]}*";
			}
			
			if (!$terms)
				continue;
			else if ($isExclude)
				$exclude[] = $terms;
			else
				// 旧形式の索引は作品ごとに重複した n-gram を省いており隣接が保たれないため、
				// フレーズでは取りこぼす。すべての n-gram を含むものを探す
				array_push($include, ...($this->phraseFormat ? $terms : $allTerms));
		}
		
		// 除外だけの条件は全文検索では表せない
		if (!$include)
			return "";
		
		$rt = implode(" ", $include);
		
		foreach ($exclude as $terms)
			if ($enhancedSyntax)
				$rt .= " NOT " . (count($terms) == 1 ? $terms[0] : "(" . implode(" AND ", $terms) . ")");
			else
				// 標準の構文ではまとめて除外できないため、いずれかの部分を含むものを除外する
				foreach ($terms as $j)
					$rt .= " -{$j}";
		
		return $rt;
	}
	
	/**
	 * 拡張構文 (AND, OR, NOT と括弧) が使えるか。標準の構文では除外は - で表す
	 */
	private static function hasEnhancedQuerySyntax(PDO $db): bool
	{
		static $rt = null;
		
		if ($rt === null)
		{
			$st = $db->query("pragma compile_options");
			$rt = $st !== false && in_array("ENABLE_FTS3_PARENTHESIS", $st->fetchAll(PDO::FETCH_COLUMN, 0));
		}
		
		return $rt;
	}

	#[\Override]
	function ensureTableExists(PDO $idb): void
	{
		$this->ensurePhraseFormat($idb, Util::createFullTextTableIfNotExists($idb, self::$searchIndexSchema, self::INDEX_TABLE));
	}

	#[\Override]
	function getExistingThread(PDO $idb): array
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select docid from %s
			group by docid',
			self::INDEX_TABLE
		)));
		Util::executeStatement($st);
		
		return array_map("intval", $st?->fetchAll(PDO::FETCH_COLUMN, 0) ?? array());
	}
}
?>
