<?php
namespace Megalopolis;

use \PDO;

class Statistics
{
	public int $subject = 0;
	public int $entryCount = 0;
	public int $maxPoints = 0;
	public float $avgPoints = 0.0;
	public int $maxEvaluations = 0;
	public float $avgEvaluations = 0.0;
	public int $maxComments = 0;
	public float $avgComments = 0.0;
	public float $avgPointPerEvaluation = 0.0;
	public float $avgEntryPerDay = 0.0;
	public float $usedDays = 0.0;
	
	/**
	 * @return Statistics[]
	 */
	static function getStatistics(PDO $db): array
	{
		return self::query($db, 'group by subject');
	}
	
	/**
	 * @return Statistics[]
	 */
	static function getStatisticsByTag(PDO $db, string $tag): array
	{
		return self::query($db, 'where tag = ? group by tag', array($tag));
	}
	
	/**
	 * @return Statistics[]
	 */
	static function getStatisticsByName(PDO $db, string $name): array
	{
		return self::query($db, 'where name = ? group by name', array($name));
	}
	
	/**
	 * @return Statistics[]
	 */
	private static function query(PDO $db, string $options = "", array $params = array()): array
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
		
		return $st?->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\Statistics") ?? array();
	}
}
?>
