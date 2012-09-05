Megalopolis

概要
====

点数評価とコメントが可能な、小説投稿用の掲示板スクリプトです。

主な機能
--------

* 投稿、コメント、点数による評価
* 本文のページ分け可、HTML 可
* 評価の平均点または 5 を基準にした Rate を算出 (5 を基準にした Rate は、((合計得点 + 25) / ((評価数 + 1) * 50)) * 10 で算出されます)
* 投稿者による分類タグ付けでのカテゴライズ
* ランダム作品表示とランダムタグ表示
* 全タグの一覧、全作者の一覧
* 作者を指定した作品一覧
* それなりの検索
* 横書き / 縦書きの切り替え
* ある程度の Megalith 形式ログと URL のサポート
* RSS 2.0 と Atom 形式でのフィード出力
* JSON 出力

想定された環境
==============

サーバ側
--------

* PHP 5.2.5 以上

クライアント側
--------------

* HTML5 および CSS2 を正常に解釈するモダンブラウザ
  IE6 および 7 は対象外となります
* iPhone および iPod touch については専用の出力となります
* 一般的な携帯電話については簡易出力となります

環境設定
========

設置
----

以下のファイルおよびディレクトリは、書き換えを許可してください。(パーミッション 666 や 777 など)

* store/
* .htaccess

設定項目
--------

config.php を編集することにより設定します。
管理者用パスワードは必ず設定してください。

URL と .htaccess の設定
-----------------------

PATH_INFO が使用可能な環境の場合、/index.php/2 などでアクセス可能になります。
使用不能な場合、同じ URL へは /index.php?path=/2 でアクセスすることになります。

.htaccess および mod_rewrite が使用可能な環境の場合、付属の .htaccess を使用し URL の index.php を省略できます。 
たとえば /index.php/2 や /index.php?path=/2 となるところを /2 などでアクセス可能にできます。

使わない場合は、.htaccess を削除してしまってもかまいません。

管理者パスワードの設定
----------------------

環境設定 $config->adminHash に正しくハッシュを設定するには、以下の手順を実行します。

1. $config->utilEnabled を true にする
2. 設置されたスクリプトの /util/hash にアクセスする
3. ボックスに設定したい任意のパスワードを入力し、[算出] をクリックする
4. 表示されたハッシュを $config->adminHash に書く

管理用ツール
------------

設置後の設定等を支援するためのモードです。
環境設定 $config->utilEnabled が true の場合のみ使用可能です。
不要な場合は無効化することを推奨します。

以下のツールは、管理者パスワードなしで使用可能です。

* パスワード用ハッシュ算出 (/util/hash)

以下のツールは、使用に管理者パスワードの入力を求められます。

* Megalith 形式のログの変換ツール (/util/convert)
* 検索インデックスの再生成ツール (/util/reindex)

Megalith からの移行
===================

Megalith http://9.dotpp.net/software/megalith/ からのログ変換機能を有しています。
二種類の方法があります。

Megalith のログを一括変換する
-----------------------------

Megalith 形式のログの変換ツール を使用し Megalopolis へログを一括変換します。
ログの容量によっては長い時間がかかることがありますが、Megalith で何らかの原因で作品集から外れてしまった作品も
再度作品集に登録できる可能性があります。

1. Megalith/ ディレクトリを作成し以下に dat/ com/ aft/ sub/ ログディレクトリをコピーします。
2. 管理者用パスワードを使用し、スクリプトの /util/convert へログインします。
3. 変換を開始します。
   変換中に失敗する場合は、環境設定の $config->convertDivision の値を変更してみるか、諦めてみてください。

なおすでに変換済みである作品は変換スキップされますが、変換中にエラーが発生した作品が存在し、残骸が残っている場合、
既に存在する作品であると判定されてスキップされる可能性もあります。
変換中にエラーが発生した場合はエラーメッセージの作品番号 id: および作品集番号 subject: を持つ作品を確認し、一度削除するなどしてください。
(作品ページにアクセスできない場合、作品集の作品一覧の管理者ログインによる一括削除機能をご利用ください)

Megalith ログを必要に応じて読み込みその場で変換する
---------------------------------------------------

Megalopolis から必要になったときに Megalith のログを読み込み使用します。
作品へアクセスされたときにその作品を変換し、更新されたデータは Megalopolis ログに記録されます。
一括変換である必要性がない場合、基本的にこちらの方法を推奨いたします。

1. Megalith/ ディレクトリを作成し以下に dat/ com/ aft/ sub/ ログディレクトリをコピーします。
2. 環境設定の $config->convertOnDemand を true にします。

その他
------

Megalith 形式の ?log=* や ?mode=read&key=*&log=* へアクセスすると Megalopolis 形式の URL へリダイレクトされます。

検索インデックスについて
========================

Megalopolis は検索のために文章を分解したインデックスを作成します。
通常これは作品内外に含まれる全テキストの約二倍、または各テキストに対して最大容量制限の約二倍になります。環境設定 $config->registerBodyToSearchIndex を false にし、
本文をインデックスに記録しないことで容量を削減することができますが、その場合本文への検索は使用できません。
また、環境設定 $config->maximumSearchIndexLength を変更することで最大容量制限を調整できます。

検索インデックスの再生成
------------------------

環境設定 $config->registerBodyToSearchIndex を変更した時などは [既存の検索インデックスをクリアしてから再生成する] の
オプションを付けて検索インデックスを再生成することで過去の作品に対しても反映できます。
検索インデックスの再生成は長い時間がかかることがあります。注意してください。

検索インデックスのアップグレード
--------------------------------

Megalopolis 第14版より、データベースの全文検索機能を使用する、より効率的な新しい検索システムを追加しました。
以前の形式の検索インデックスが存在する場合は以前と同等の検索システムが使用されます。
[既存の検索インデックスをクリアしてから再生成する] のオプションを付けてインデックスを再生成することで、新しい形式にアップグレードできます。

新しい形式を利用するには、サーバにインストールされている PHP で使用されている SQLite が全文検索 FTS3 または FTS4 をサポートするものであるか、
データストアに MySQL を使用している必要があります。全文検索が利用不可能な場合は新しい形式は使用できず、以前の形式が使用されます。
どちらの形式が使用されていて、利用可能であるかは管理者ログイン後、設定情報の [currentSearch] および [availableSearch] から確認できます。
[currentSearch] が classic のとき、[availableSearch] が classic でないときはアップグレードが可能です。

その他
======

任意の HTML 文章を Megalopolis のコンテンツとして表示する
---------------------------------------------------------

store/notice/ 以下に任意の .txt ファイルを置くことで、スクリプトの /notice/ から表示できます。
例えば、store/notice/sample.txt はスクリプトの /notice/sample から表示できます。
また、HTML タグが使用できます。

データストアに SQLite ファイルではなく MySQL データベースを使用する
-------------------------------------------------------------------

Megalopolis は通常 store/ 以下に SQLite データベースファイルを作成しデータを記録しますが、
規模が大きいなどの理由で通常の DBMS を用いたい場合、MySQL を使用できます。
MySQL にデータベースを作成し、レコードやテーブルが操作可能なように権限設定したユーザを追加した後、環境設定の $config->dataStore を以下のいずれかに設定します。

1. new MySQLDataStore("データベース名", array("ホスト名", ポート), "ユーザ名", "パスワード")
2. new MySQLDataStore("データベース名", "UNIX ソケットパス", "ユーザ名", "パスワード");

ポート番号は通常 3306 です。UNIX ソケットパスは通常 /tmp/mysql.sock です。

注意
----

* 縦書き表示は IE もしくは Mac 上の Safari において一番きれいに表示されます。
  WebKit 上ではスクロールバーが正常に表示されない可能性があります。
* .htaccess などでアクセスの制限が可能な環境の場合、セキュリティなどの理由により
  store/ や req/、Megalith/ 以下へのアクセスを制限することを推奨します。
  付属の .htaccess では既定で *.sqlite ファイルへのアクセスを制限する設定がなされています。

更新履歴
--------

changelog.txt をご覧ください。

ライセンス
----------

本プログラムはフリーウェアです。完全に無保証で提供されるものであり
これを使用したことにより発生した、または発生させた、あるいは
発生させられたなどしたいかなる問題に関して製作者は一切の責任を負いません。
別途ライセンスが明記されている場所またはファイルを除き、使用者は本プログラムを
Do What The Fuck You Want To Public License, Version 2 (WTFPL) および自らの責任において
自由に複製、改変、再配布、などが可能です。WTFPL についての詳細は次の URL か、
以下の条文を参照してください。http://sam.zoy.org/wtfpl/

            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
                    Version 2, December 2004 

 Copyright (C) 2004 Sam Hocevar <sam@hocevar.net> 

 Everyone is permitted to copy and distribute verbatim or modified 
 copies of this license document, and changing it is allowed as long 
 as the name is changed. 

            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION 

  0. You just DO WHAT THE FUCK YOU WANT TO.

ライブラリ
----------

* jQuery http://jquery.com/ および jQuery Mobile http://jquerymobile.com/ を使用しています。
* Simple HTML Parser http://sourceforge.net/projects/simplehtmldom/ を使用しています。
* PHP Classes CSS parser を使用しています。
* 一部の PC 向けに縦書きエンジンとして 竹取 JS http://taketori.org/js.html を使用しています。
* 携帯向けに縦書きエンジンとして Nehan 2 http://code.google.com/p/nehan/ を使用しています。
* 携帯向けに ChocolateChip-UI http://www.chocolatechip-ui.com/ のアイコンを使用しています。

連絡先
------

* 製作: COAH96KoxU <queue@glasscore.net> http://9.dotpp.net/