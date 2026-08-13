# HC Platform Production Status

更新日: 2026-08-13

## 概要

本番環境でゲームサーバー自動購入・Pterodactyl連携・スタッフサポート周辺の統合テストを実施。

本番で発見した不整合を修正中。

---

## 1. 問い合わせ / Staff Support

### 発生した問題

`contacts` テーブルと Staff Support 実装のスキーマに差異があった。

不足していたカラム:

- handled_at
- assigned_to

また、`contacts_status_check` が古く、

- open
- in_progress
- closed

のみ許可していた。

現在のアプリケーションは以下を使用する。

- open
- in_progress
- waiting
- resolved
- closed

### 対応

本番DBへ以下を追加済み。

- contacts.handled_at
- contacts.assigned_to
- contacts_status_check 更新

Migrationへ反映する。

### 確認済みエラー

以前:

SQLSTATE[42703]
column handled_at does not exist

SQLSTATE[42703]
column contact.assigned_to does not exist

SQLSTATE[23514]
contacts_status_check violation

---

## 2. Stripe決済

### 状況

Stripe Checkout自体は成功する。

注文 #4 で実決済確認。

ただしStripe Webhook側に旧実装が残っていた。

旧コード:

stripe_config()

現在の `lib/stripe.php` には `stripe_config()` は存在しない。

現在のStripe実装:

- hc_stripe_webhook_secret()
- hc_stripe_verify_webhook_signature()
- hc_stripe_request()

### 発生していたエラー

Call to undefined function stripe_config()

webhooks/stripe/index.php

### 本番修正

Webhookを現行Stripeライブラリを使用する実装へ変更。

### 未解決

本番環境に以下が未設定。

STRIPE_WEBHOOK_SECRET

Stripe Dashboardで発行された `whsec_...` を
`/etc/hc-platform/app.env` に設定する必要がある。

秘密値はGitに保存しない。

---

## 3. ゲームサーバー自動作成

### 注文 #4

注文:

kakuni-hc-sv

状態:

payment_status = paid

Stripe決済は成功。

しかし `provisioning_jobs` にジョブが生成されていなかった。

原因:

Webhook処理が正常に最後まで実行されていなかった。

### 手動復旧

Order #4 にProvisioning Jobを投入。

Job:

#10

Worker手動実行:

php bin/provision-worker.php

結果:

Job #10 完了

---

## 4. Provision Worker

### 問題

本番サーバーでProvision Workerが常駐していなかった。

以下は空だった。

ps aux | grep provision-worker

systemctl list-units --type=service | grep provision

そのためProvisioning Jobがpendingになっても自動処理されない。

### 対応方針

systemd:

hc-provision-worker.service

として常駐させる。

---

## 5. Pterodactyl / Java

### 問題

HCから作成されたMinecraftサーバーが全て:

ghcr.io/pterodactyl/yolks:java_21

を使用していた。

Minecraft 26.1+ ではJava 25が必要なため起動失敗。

エラー:

Minecraft 26.1 and newer requires running the server with Java 25 or above.

### 既存Pterodactylサーバー

以下をJava 25へ変更済み。

- #9 kakuni-mc01
- #10 test1
- #11 kakuni-hc-sv

変更後:

ghcr.io/pterodactyl/yolks:java_25

### HCプラン

全5プランについて:

game_server_plans.ptero_docker_image

を

ghcr.io/pterodactyl/yolks:java_25

へ変更済み。

対象:

- Entry 2GB
- Light 4GB
- Standard 8GB
- High Clock 16GB
- High Clock 32GB

### コード

`lib/game_server_provisioning.php`

デフォルト:

java_21

から

java_25

へ変更。

### 今後

MinecraftバージョンごとにJavaランタイムを自動選択する方式へ変更予定。

---

## 6. Staff Notifications

未解決。

確認済みエラー:

SQLSTATE[42703]
column "source" does not exist

Staff Notifications APIとDBスキーマに不整合がある。

Migration / APIの整合修正が必要。

---

## 7. PCRE JIT

本番Apache/PHPで以下の警告が継続。

Allocation of JIT memory failed, PCRE JIT will be disabled.

候補対応:

pcre.jit=0

PHP設定側で対応予定。

---

## 8. Notification mark-read

`/dashboard/notifications/mark-read.php`

へのPOSTがprd-edge01のNginxルールによって、

.php付きURL
→ .php削除301
→ extensionless URL
→ 404

となっていた。

prd-edge01でmark-read.phpをWeb01へ直接proxyする例外を追加。

今後、全PHP POST/APIエンドポイントに対して
グローバル `.php` 削除ルールの影響を監査する必要がある。

---

## 9. Pterodactyl通信

過去にPanel -> WingsのServer Details APIでタイムアウトを確認。

Panel:

Guzzle connect timeout

対象:

/api/servers/{uuid}

Wings側ではリクエスト処理自体は高速に200を返していた。

必要に応じてTCP / proxy経路を追加調査する。

---

## Production TODO

優先度高:

1. STRIPE_WEBHOOK_SECRET設定
2. Stripe Webhook再送テスト
3. hc-provision-worker systemd常駐
4. 新規購入E2Eテスト
5. Staff Notifications source列不整合修正

次:

6. PCRE JIT設定修正
7. .php削除Nginxルール監査
8. Minecraft Javaバージョン自動選択
9. Pterodactyl Panel-Wings timeout調査

---

## セキュリティ

以下はGitに保存しない。

- .env
- Stripe Secret Key
- Stripe Webhook Secret
- Pterodactyl API Key
- Wings Token
- Cloudflare Tunnel Token
