# Megalopolis

[![Tests](https://github.com/mfakane/Megalopolis/actions/workflows/test.yml/badge.svg)](https://github.com/mfakane/Megalopolis/actions/workflows/test.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://www.php.net/)
[![License: WTFPL](https://img.shields.io/badge/License-WTFPL-brightgreen.svg)](http://www.wtfpl.net/)

**Megalopolis** は、点数評価とコメント機能を備えた小説投稿用の高機能 Web 掲示板スクリプトです。  
シンプルな運用から大規模な運用まで、柔軟に対応できるように設計されています。

---

## 目次

- [特徴](#特徴)
- [動作環境](#動作環境)
- [インストール・セットアップ](#インストールセットアップ)
  - [1. ファイルの配置と権限設定](#1-ファイルの配置と権限設定)
  - [2. 基本設定 (config.php)](#2-基本設定-configphp)
  - [3. 管理者パスワードの設定](#3-管理者パスワードの設定)
  - [4. Web サーバの設定 (URL 簡略化・アクセス制限)](#4-web-サーバの設定-url-簡略化アクセス制限)
- [データストア (SQLite / MySQL)](#データストア-sqlite--mysql)
- [管理機能・管理ツール](#管理機能管理ツール)
- [Megalith からの移行](#megalith-からの移行)
- [検索インデックス](#検索インデックス)
- [アップデート手順](#アップデート手順)
- [開発・テスト](#開発テスト)
- [その他の機能・仕様](#その他の機能仕様)
- [ライセンス・クレジット](#ライセンスクレジット)

---

## 特徴

- **作品投稿・閲覧**
  - 本文の複数ページ分割対応、HTML タグの利用可能
  - 横書き／縦書きの表示切り替え対応
  - PC およびスマートフォンのレスポンシブ表示対応
  - OGP / Twitter Card メタタグ出力対応
- **評価・コメントシステム**
  - 得点評価（簡易評価）、コメントのみ、コメント付き得点評価に対応
  - 評価の平均点および「5」を基準にした独自 Rate 算出機能
    - 算出式: `((合計得点 + 25) / ((評価数 + 1) * 50)) * 10`
- **作品集（アーカイブ）管理**
  - 投稿数に応じた作品集（ページ単位）の固定ナンバリング
  - 過去作品の削除が発生しても作品集番号がずれない不変設計
- **タグ・分類・検索**
  - 投稿者による分類タグ付け
  - 全タグ一覧、全作者一覧、作者別作品一覧、ランダム作品／タグ表示
  - SQLite (FTS3/FTS4) / MySQL による高速な全文検索
- **フィード・エクスポート**
  - RSS 2.0 / Atom 形式のフィード出力（例: `0.rss` で最新作品集）
  - 作品集ごとの CSV ダウンロード（例: `15.csv`）
- **移行・後方互換性**
  - [Megalith](http://9.dotpp.net/software/megalith/) からのログ一括変換およびオンデマンド変換に対応
  - 旧 Megalith 形式の URL からのリダイレクトをサポート

---

## 動作環境

### サーバ環境
- **PHP**: 8.4 以上
- **必須/推奨 PHP 拡張**:
  - `pdo_sqlite` (SQLite 使用時)
  - `pdo_mysql` (MySQL 使用時)
  - `mbstring`
  - `dom`
- **Web サーバ**:
  - Apache 2.2 / 2.4+ (mod_rewrite, PATH_INFO 推奨)
  - nginx

### クライアント環境
- モダンブラウザ（PC / スマートフォン）

---

## インストール・セットアップ

### 1. ファイルの配置と権限設定

スクリプト一式を Web サーバの公開ディレクトリに配置します。  
以下のディレクトリおよびファイルに Web サーバプロセスの書き込み権限（パーミッション `777` または `666` など環境に応じた適切な権限）を付与してください。

- `store/`
- `.htaccess`

> [!WARNING]
> `store/` にはデータベースファイルやアクセスログが保存されます。Web ブラウザから直接アクセスできないよう、必ず後述のアクセス制限を行ってください。

### 2. 基本設定 (config.php)

`config.php` をテキストエディタで開き、サイト名や動作オプションを環境に合わせて設定します。

### 3. 管理者パスワードの設定

安全な運用のため、管理者パスワードのハッシュを設定してください。

1. `config.php` 内の `$config->utilsEnabled` を `true` に設定します。
2. ブラウザで `/util/hash`（例: `https://example.com/util/hash`）にアクセスします。
3. 任意のパスワードを入力して **[算出]** をクリックします。
4. 表示されたコード行をコピーし、`config.php` の `$config->adminHash` を置き換えます。
   ```php
   $config->adminHash = '...算出されたハッシュ文字列...';
   ```
   > [!NOTE]
   > ハッシュ文字列には `$` が含まれるため、文字列リテラルはダブルクォート (`"`) ではなくシングルクォート (`'`) で囲んでください。
5. 設定完了後、セキュリティのため `$config->utilsEnabled` を `false`（または必要時のみ有効）に戻すことを推奨します。

### 4. Web サーバの設定 (URL 簡略化・アクセス制限)

#### Apache の場合
付属の `.htaccess` を利用することで、`mod_rewrite` により `index.php` を省略した URL（例: `/2`）でアクセスできます。  
`store/` 直下の `.htaccess` により、データベースファイル等へのアクセスは自動的に拒否されます。

#### nginx の場合
nginx では `.htaccess` が機能しないため、サーバブロック設定に以下を追加して機密ディレクトリ・ファイルへの直接アクセスを遮断してください。

```nginx
# 機密ディレクトリ・ファイルのアクセス拒否
location ^~ /store/ { deny all; }
location ~ \.sqlite$ { deny all; }
location ^~ /req/ { deny all; }
location ^~ /Megalith/ { deny all; }

# PATH_INFO / rewrite の設定例
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_pass 127.0.0.1:9000;
}
```

---

## データストア (SQLite / MySQL)

### SQLite（デフォルト）
標準では `store/` 直下に `*.sqlite` 形式で自動生成されます。追加の設定なしですぐに利用可能です。

保存先ディレクトリを Web 公開領域外に変更したい場合は、`config.php` で以下のように指定します:
```php
$config->dataStore = new SQLiteDataStore("../store/");
```

### MySQL
アクセス規模が大きい場合など、RDBMS として MySQL を使用することも可能です。  
データベースおよび操作権限を持つユーザを作成した上で、`config.php` の `$config->dataStore` を設定します。

**TCP/IP 接続の場合:**
```php
$config->dataStore = new MySQLDataStore("データベース名", array("ホスト名", 3306), "ユーザ名", "パスワード");
```
*(ローカルホストの場合、`localhost` よりも `127.0.0.1` と指定したほうが接続が高速になる場合があります)*

**UNIX ソケット接続の場合:**
```php
$config->dataStore = new MySQLDataStore("データベース名", "/tmp/mysql.sock", "ユーザ名", "パスワード");
```

#### MySQL の文字コード (utf8mb4)
新規作成されるテーブルは `utf8mb4`（照合順序 `utf8mb4_bin`）となり、絵文字等もそのまま保存されます。  
旧バージョンの `utf8` テーブルを使用している場合は、管理用ツール `/util/charset` から安全に `utf8mb4` への移行が可能です。

---

## 管理機能・管理ツール

### 管理者ログイン
ページフッタの「ログイン」メニューから管理者ログインが可能です。
- **管理者権限で実行できる主な操作:**
  - 表示設定に関わらない全作品・名前・コメント・評価数の確認
  - 投稿元ホスト情報の確認
  - 作品・コメント・評価の一括削除
  - 編集キーなしでの作品編集・コメント削除
  - 管理ツールの利用

### 管理用設定ツール (`/util/`)
`$config->utilsEnabled = true;` の場合に利用可能です。

| パス | 説明 | 認証 |
| :--- | :--- | :--- |
| `/util/hash` | 管理者パスワード用のハッシュ文字列を算出 | 不要 |
| `/util/convert` | Megalith 形式のログを一括変換 | 管理者パスワード |
| `/util/convert/tags` | Megalith 形式のタグ順序をインポート | 管理者パスワード |
| `/util/reindex` | 検索インデックスの再構築・最適化 | 管理者パスワード |
| `/util/charset` | データベースの文字コード変換 (MySQL: utf8 → utf8mb4) | 管理者パスワード |

---

## Megalith からの移行

[Megalith](http://9.dotpp.net/software/megalith/) からのデータ移行方法には 2 種類あります。

### 方法 1: 必要に応じてその場で変換（推奨）
作品への初回アクセス時に自動で Megalith ログを読み込んで Megalopolis 形式に変換します。
1. `Megalith/` ディレクトリを作成し、その中に `dat/`, `com/`, `aft/`, `sub/` ログディレクトリをコピーします。
2. `config.php` の `$config->convertOnDemand` を `true` に設定します。

### 方法 2: 一括変換ツールを利用
過去の作品を一括でインポートします。
1. `Megalith/` ディレクトリ配下に `dat/`, `com/`, `aft/`, `sub/` を配置します。
2. 管理者パスワードで `/util/convert` にログインし、変換を実行します。
   *(途中でタイムアウトする場合は `$config->convertDivision` で分割数を調整してください)*

---

## 検索インデックス

Megalopolis は高速な語句検索のために検索インデックスを生成します。

- **容量の節約**: 本文を検索対象から除外して容量を節約したい場合は、`$config->registerBodyToSearchIndex = false;` に設定します。
- **インデックス再生成**: 設定変更時やインデックス破損時は、`/util/reindex` から「既存の検索インデックスをクリアしてから再生成する」を実行してください。
- **全文検索エンジン**: SQLite FTS3/FTS4 または MySQL 全文検索が自動判別・利用されます。

---

## アップデート手順

1. **バックアップ**: `store/` ディレクトリ（または MySQL データベース）および `config.php` のバックアップを取得します。
2. **ファイルの上書き**: `store/`, `.htaccess`, `config.php` 以外のファイルを新しいバージョンで上書きします。
3. **設定の確認**: 新機能用の設定項目が追加されている場合があるため、同梱の新しい `config.php` と見比べて必要に応じて差分を反映します。
4. **自動マイグレーション**: 更新後にブラウザからアクセスすると、必要なテーブル構造の変更が自動的に実行されます。
5. **移行ツールの確認**: 必要に応じて管理者ログインし、管理用ツールにてインデックス再生成や文字コード移行の案内が出ていないか確認します。

---

## 開発・テスト

本プロジェクトでは Composer による依存関係管理、Psalm による静的解析、PHPUnit によるユニットテスト、Playwright による E2E ブラウザテストを採用しています。

### セットアップ
```bash
composer install
```

### コマンド一覧

| コマンド | 内容 |
| :--- | :--- |
| `composer analyse` | Psalm による静的解析の実行 |
| `composer test` | PHPUnit による単体テストの実行 |
| `composer test:browser` | Playwright による E2E ブラウザテストの実行 |
| `composer test:compat` | 過去バージョンとの互換性テストの実行 |
| `composer test:upgrade` | データベースのアップグレード検証テストの実行 |

---

## その他の機能・仕様

- **お知らせ機能**: `store/notice/` に `.txt` ファイル（HTML タグ使用可）を配置することで、`/notice/ファイル名` で固定ページとして表示できます（例: `store/notice/rules.txt` → `/notice/rules`）。
- **ブラウザキャッシュ**: 作品集や作品閲覧ページでは `Last-Modified` および `ETag` ヘッダを出力し、表示の高速化を図っています。`config.php` 等の変更時に自動でキャッシュが無効化されます。
- **更新履歴**: 詳細な変更履歴は [changelog.txt](changelog.txt) を参照してください。

---

## ライセンス・クレジット

### ライセンス
本スクリプト本体は [WTFPL (Do What The Fuck You Want To Public License) Version 2](http://sam.zoy.org/wtfpl/) のもとで公開されています。

```text
            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION 

  0. You just DO WHAT THE FUCK YOU WANT TO.
```

### サードパーティライブラリ
- **jQuery** - [http://jquery.com/](http://jquery.com/)
- **vlucas/phpdotenv** (BSD-3-Clause)
- **vimeo/psalm** (MIT)
- **phpunit/phpunit** (BSD-3-Clause)

詳細なライセンス表記は [THIRD_PARTY_LICENSES.txt](THIRD_PARTY_LICENSES.txt) をご覧ください。

### 作者・連絡先
- **製作**: COAH96KoxU (`<queue@glasscore.net>`)
- **リポジトリ**: [https://github.com/mfakane/Megalopolis](https://github.com/mfakane/Megalopolis)
