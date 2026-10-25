<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

/** A browser bound to an isolated upgrade service. */
final class UpgradeBrowser extends Browser
{
    public function __construct(string $service)
    {
        if (getenv('COMPAT_HTTP') !== '1' || getenv('COMPAT_UPGRADE') !== '1') {
            throw new \RuntimeException('Upgrade tests require: bash compat/upgrade.sh');
        }
        if (!preg_match('/^(baseline|candidate|restored)-(sqlite|mysql)$/', $service)) {
            throw new \InvalidArgumentException('Not an isolated upgrade service');
        }
        parent::__construct($service);
    }

    public function snapshot(?int $id = null): array
    {
        $response = $this->request('GET', '', ['upgradeSnapshot' => '1'] + ($id === null ? [] : ['id' => $id]));
        if ($response['status'] !== 200 || !isset($response['json']['works'])) {
            throw new \RuntimeException('Snapshot failed: ' . substr($response['body'], 0, 1500));
        }
        return $response['json'];
    }
}
