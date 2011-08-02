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
* 作者を指定した作品一覧と、統計情報
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

* Megalith 形式のログの変換 (/util/convert)

Megalith 形式のログの変換ツール
-------------------------------

Megalith/ のディレクトリを作成しその中に dat/ com/ aft/ sub/ の Megalith ログディレクトリをコピーすると
スクリプトの /util/convert から Megalopolis へログをインポートできます。

Megalith からの移行
===================

Megalith http://9.dotpp.net/software/megalith/ からのログ変換機能を有しています。
二種類の方法があります。お好きな方をどうぞ。

Megalith のログを一括変換する
-----------------------------

Megalith 形式のログの変換ツール を使用し Megalopolis へログを一括変換します。
ログの容量によっては長い時間がかかることがありますが、Megalith で何らかの原因で作品集から外れてしまった作品も
再度作品集に登録できる可能性があります。

1. Megalith/ ディレクトリを作成し以下に dat/ com/ aft/ sub/ ログディレクトリをコピーします。
2. 管理者用パスワードを使用し、スクリプトの /util/convert へログインします。
3. 変換を開始します。
   変換中に失敗する場合は、環境設定の $config->convertDivision の値を変更してみるか、諦めてみてください。

Megalith ログを必要に応じて読み込みその場で変換する
---------------------------------------------------

Megalopolis から必要になったときに Megalith のログを読み込み使用します。
更新したデータは Megalopolis ログに記録されます。

1. Megalith/ ディレクトリを作成し以下に dat/ com/ aft/ sub/ ログディレクトリをコピーします。
2. 環境設定の $config->convertOnDemand を true にします。

その他
======

注意
----

* 縦書き表示は IE もしくは Mac 上の Safari において一番きれいに表示されます。
  WebKit 上ではスクロールバーが正常に表示されない可能性があります。
* .htaccess などでアクセスの制限が可能な環境の場合、セキュリティなどの理由により
  store/ 以下へのアクセスを制限することを推奨します。

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