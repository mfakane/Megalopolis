<?php

namespace Megalopolis;

use \PDO;

class MySQLSearchIndex extends SQLiteSearchIndex
{
	function __construct()
	{
		$this->gramLength = max(Configuration::$instance->mysqlSearchNgramLength, 2);
	}

	#[\Override]
	function registerThread(PDO $idb, Thread $thread, bool $removeExisting): void
	{
		if ($thread->id === 0)
			return;

		// 消した欄 (あとがきやタグなど) の古い語が残らないよう、空の欄も含めてすべての欄を更新する
		/** @var array<string, string[]> */
		$words = array(
			"title" => $this->getWords([$this->phraseWords(), self::encodeSupplementary($thread->entry->title)]),
			"name" => $this->getWords([$this->phraseWords(), self::encodeSupplementary($thread->entry->name)]),
			"summary" => $this->getWords([$this->phraseWords(), self::encodeSupplementary($thread->entry->summary)]),
			"body" => Configuration::$instance->registerBodyToSearchIndex ? $this->getWords([$this->phraseWords(), self::encodeSupplementary($thread->body)]) : array(),
			"afterword" => $this->getWords([$this->phraseWords(), self::encodeSupplementary($thread->afterword)]),
			"tag" => $this->getWords(array_merge([$this->phraseWords()], array_map(fn($x) => self::encodeSupplementary($x), $thread->entry->tags)))
		);

		foreach ($words as $k => $v)
			$words[$k] = $this->padWords($v);

		$st = Util::ensureStatement($idb, $idb->prepare(sprintf(
			'
			insert into %s(docid, %s)
			values
			(
				%d,
				%s
			)
			on duplicate key update %s;',
			self::INDEX_TABLE,
			implode(", ", array_keys($words)),
			$thread->id,
			implode(", ", array_map(function ($_) {
				return ":{$_}";
			}, array_keys($words))),
			implode(", ", array_map(function ($_) {
				return "{$_} = values({$_})";
			}, array_keys($words)))
		)));
		Util::executeStatement($st, array_map(function ($_) {
			return implode(" ", $_);
		}, $words));
	}

	/**
	 * MyISAM の全文検索は 4 バイトの文字を語の一部として扱わないため、
	 * 索引でも検索語でも数値文字参照の形にする (数字の部分で検索できる)
	 */
	private static function encodeSupplementary(?string $s): ?string
	{
		return $s === null ? null : (preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => "&#" . (int)mb_ord($m[0], "UTF-8") . ";", $s) ?? $s);
	}

	/**
	 * 全文検索の最小語長より短い n-gram を _ で埋める
	 * @param string[] $words
	 * @return string[]
	 */
	function padWords(array $words): array
	{
		// str_pad はバイト数で数えるため、マルチバイト文字の n-gram が埋められない
		return array_map(fn($_) => $_ . str_repeat("_", max($this->gramLength - mb_strlen($_), 0)), $words);
	}

	#[\Override]
	function attachIndexCore(PDO $idb): void
	{
		if (!Configuration::$instance->dataStore instanceof MySQLDataStore)
			throw new ApplicationException("DataStore is not MySQLDataStore");

		Configuration::$instance->dataStore->attachFullTextIndex($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}

	#[\Override]
	function detachIndexCore(PDO $idb): void
	{
		if (!Configuration::$instance->dataStore instanceof MySQLDataStore)
			throw new ApplicationException("DataStore is not MySQLDataStore");

		Configuration::$instance->dataStore->detachFullTextIndex($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}

	#[\Override]
	function searchThread(PDO $idb, array $query, ?array $type = null, ?array $ids = null): array
	{
		if (!$query)
			return array();

		if (!($booleanQuery = $this->buildBooleanQuery($query)))
			return array();

		$targetColumns = $type == null ? array_keys(self::$searchIndexSchema) : $type;
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf(
			'
			select docid from
			(' . implode(" union ", array_map(function ($_) {
				return "select docid from %2\$s where match({$_}) against(? in boolean mode)";
			}, $targetColumns)) . ') as search %1$s',
			is_array($ids) ? "where docid in (" . ($ids ? implode(", ", $ids) : -1) . ")" : "",
			self::INDEX_TABLE
		)));
		Util::executeStatement($st, array_fill(0, count($targetColumns), $booleanQuery));

		return $st?->fetchAll(PDO::FETCH_COLUMN, 0) ?? array();
	}

	/**
	 * 検索語から MATCH ... AGAINST の boolean mode 用の条件を作る
	 * @param string[] $query
	 */
	function buildBooleanQuery(array $query): string
	{
		$queryArguments = array();

		foreach ($query as $i) {
			$exclude = strpos($i, "-") === 0;

			if ($exclude)
				$i = substr($i, 1);

			$i = (string)self::encodeSupplementary($i);

			// 索引は記号などで区切った部分ごとに n-gram にしているため、部分ごとに条件にする
			$terms = array();

			foreach (self::splitSegments($i) as $segment) {
				$words = $this->getWords([$this->phraseWords() + array("endOnIncompletedGram" => true, "noIncompletedGram" => mb_strlen($segment) >= $this->gramLength), $segment]);

				if (!$words)
					continue;
				else if (mb_strlen($words[0]) == $this->gramLength)
					$terms[] = '"' . implode(" ", $words) . '"';
				else
					$terms[] = $words[0] . (Configuration::$instance->mysqlSearchUseHeadMatching ? "*" : str_repeat("_", $this->gramLength - mb_strlen($words[0])));
			}

			if (!$terms)
				continue;
			else if (!$exclude)
				foreach ($terms as $j)
					$queryArguments[] = "+{$j}";
			else if (count($terms) == 1)
				$queryArguments[] = "-{$terms[0]}";
			else
				$queryArguments[] = "-(+" . implode(" +", $terms) . ")";
		}

		return implode(" ", $queryArguments);
	}

	#[\Override]
	function unregisterThread(PDO $idb, array $ids): void
	{
		$count = count($ids);

		if ($count == 1)
			Util::executeStatement(Util::ensureStatement($idb, $idb->prepare(sprintf(
				'
				delete from %s
				where docid = ?
				limit %d',
				self::INDEX_TABLE,
				$count
			))), array($ids[0]));
		else
			Util::executeStatement(Util::ensureStatement($idb, $idb->prepare(sprintf(
				'
				delete from %s
				where docid in (%s)
				limit %d',
				self::INDEX_TABLE,
				implode(", ", array_map('intval', $ids)),
				$count
			))));
	}

	#[\Override]
	function getEntryCountCore(PDO $idb): ?int
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf(
			'
			select count(*) from %s',
			self::INDEX_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0) ?? array(0);

		return intval(array_shift($rt));
	}
}
