<?php
namespace Megalopolis;

use \PDO;

/**
 * テーブルの構造の更新 (マイグレーション)。
 * 更新後に最初に開いたリクエストがロックを取って一度だけ行い、その間ほかのリクエストは
 * 少し待つか、メンテナンス中 (503) と表示する。失敗した場合は管理者が再実行するまで
 * 自動では繰り返さない
 */
class Migration
{
	const string VERSION_META_NAME = "schemaVersion";
	const string ERROR_META_NAME = "schemaMigrationError";
	/** ほかのリクエストが更新し終えるのを待つ秒数 */
	static int $lockWait = 10;

	/** 更新の途中で異常終了したことを記録するため */
	private static bool $running = false;

	/**
	 * 各テーブルの構造の版をまとめたもの。いずれかの版を上げると、次のリクエストで更新する
	 */
	static function latestVersion(): string
	{
		return implode(".", array(
			ThreadEntry::$threadEntrySchemaVersion,
			ThreadEntry::$threadEvaluationSchemaVersion,
			ThreadEntry::$threadTagSchemaVersion,
			ThreadEntry::$authorSchemaVersion,
			ThreadEntry::$tagSchemaVersion,
			Thread::$threadSchemaVersion,
			Thread::$threadStyleSchemaVersion,
		));
	}

	static function isLatest(PDO $db): bool
	{
		return Meta::get($db, self::VERSION_META_NAME) === self::latestVersion();
	}

	static function getError(PDO $db): ?string
	{
		return Meta::get($db, self::ERROR_META_NAME);
	}

	/**
	 * 必要なら更新する
	 * @param bool $retry 前回失敗していても再実行する (管理者による実行)
	 * @return bool 最新の構造になっているか
	 */
	static function ensure(PDO $db, DataStore $store, bool $retry = false): bool
	{
		if (self::isLatest($db))
			return true;

		if (!$retry && self::getError($db) !== null)
			return false;

		if (!$store->lockMigration($db, self::$lockWait))
			return false;

		self::$running = true;

		try {
			// 待っている間に、ほかのリクエストが更新し終えているかもしれない
			if (Meta::refresh($db, self::VERSION_META_NAME) === self::latestVersion())
				return true;

			if (!$retry && Meta::refresh($db, self::ERROR_META_NAME) !== null)
				return false;

			// 大きなデータベースでは時間がかかる。途中で止まると次のリクエストがやり直す
			set_time_limit(0);
			register_shutdown_function(function () use ($db) {
				$error = error_get_last();

				if (self::$running && $error !== null)
					self::recordError($db, $error["message"]);
			});

			$store->migrate($db, fn() => Board::ensureTable($db));
			Meta::set($db, self::ERROR_META_NAME, null);
			Meta::set($db, self::VERSION_META_NAME, self::latestVersion());

			return true;
		} catch (\Throwable $ex) {
			error_log((string)$ex);
			// 取り消された版の値を覚えたままにしない
			Meta::$meta = array();
			self::recordError($db, $ex->getMessage());

			return false;
		} finally {
			self::$running = false;
			$store->unlockMigration($db);
		}
	}

	private static function recordError(PDO $db, string $message): void
	{
		try {
			Meta::set($db, self::ERROR_META_NAME, mb_strimwidth($message, 0, 250, "…"));
		} catch (\Throwable) {
			// 記録できなければ、次のリクエストがやり直す
		}
	}
}
?>
