<?php
class Statistics
{
	public $subject = 0;
	public $entryCount = 0;
	public $maxPoints = 0;
	public $avgPoints = 0.0;
	public $maxEvaluations = 0;
	public $avgEvaluations = 0.0;
	public $maxComments = 0;
	public $avgComments = 0.0;
	public $avgPointPerEvaluation = 0.0;
	public $avgEntryPerDay = 0.0;
	public $usedDays = 0.0;
	
	/**
	 * @return array of Statistics
	 */
	static function getStatistics(PDO $db)
	{
		return self::query($db, 'group by subject');
	}
	
	/**
	 * @param string $tag
	 * @return array of Statistics
	 */
	static function getStatisticsByTag(PDO $db, $tag)
	{
		return self::query($db, 'where tag = ? group by tag', array($tag));
	}
	
	/**
	 * @param string $name
	 * @return array of Statistics
	 */
	static function getStatisticsByName(PDO $db, $name)
	{
		return self::query($db, 'where name = ? group by name', array($name));
	}
	
	/**
	 * @param string $options [optional]
	 * @return array of ThreadEntry
	 */
	private static function query(PDO $db, $options = "", array $params = array())
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select
				subject,
				count(1) as entryCount,
				max(points) as maxPoints,
				avg(points) as avgPoints,
				max(evaluationCount) as maxEvaluations,
				avg(evaluationCount) as avgEvaluations,
				max(commentCount) as maxComments,
				avg(commentCount) as avgComments,
				avg(points) / avg(evaluationCount) as avgPointPerEvaluation,
				count(1) / max(1, (max(dateTime) - min(dateTime)) / 86400.0) as avgEntryPerDay,
				(max(dateTime) - min(dateTime)) / 86400.0 as usedDays
			from %s
			left join %s on %1$s.id = %2$s.id
			left join %s on %1$s.id = %3$s.id
			%s',
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			App::THREAD_TAG_TABLE,
			$options
		)));
		Util::executeStatement($st, $params);
		
		return $st->fetchAll(PDO::FETCH_CLASS, "Statistics");
	}
}
?>
