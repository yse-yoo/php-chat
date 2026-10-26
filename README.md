# php-chat

next-chat のチャット部分を PHP / JS / Tailwind で再実装したもの。
`next-chat-server`（Socket.IO）と `next-chat` と同じ MySQL（`users` テーブル）を共有する。

## セットアップ（ローカル / php -S）
1. `.env.sample` を `.env` にコピーし、`DB_*` / `JWT_SECRET`（next-chat-server と同じ値）/ `GEMINI_API_KEY` を設定
2. next-chat-server を起動（`CORS_ORIGIN` に `http://localhost:8000` を追加）
3. `php -S localhost:8000 -t public`
4. http://localhost:8000 を開く（要 `pdo_mysql`, `curl` 拡張）

ユーザ登録は next-chat 側で行う（このプロジェクトにはログインのみ）。

## Docker（LAMP）で動かす

`C:\Users\youhei_yoshikawa\projects\docker\lamp`（php:8.2-apache + MySQL）を使う。
`projects` ディレクトリ全体がコンテナの `/var/www/html` にマウントされるため、他のプロジェクトと同居する。

### mod_rewrite は不要
`public/` は URL とファイルが 1 対 1 の素の PHP で、`.htaccess` も使わない。
必要なのは「`public/` だけを公開する」ことで、`lib/` や `.env` は公開しない（DocumentRoot の外に置く）。

### アクセス先
| URL | 内容 |
|---|---|
| `http://chat.local/` | この PC のみ。`public/` を DocumentRoot にした専用 VirtualHost |
| `http://172.16.2.25/php-chat/` | 学内ネットワークの他 PC から。`Alias /php-chat` で公開 |
| `http://172.16.2.25/` | 他プロジェクトの一覧（従来どおり） |

`docker/lamp/app/000-default.conf`:

```apache
<VirtualHost *:80>
    ServerName adminer.local          # 先頭 = default。localhost / IP 直打ちはここに来る
    DocumentRoot /var/www/html
    ...
    Alias /php-chat /var/www/html/chat/php-chat/public
    <Directory /var/www/html/chat/php-chat/public>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName chat.local
    DocumentRoot /var/www/html/chat/php-chat/public
    ...
</VirtualHost>
```

設定を変えたら **再ビルドが必要**（`000-default.conf` はイメージに `ADD` されるため、再起動だけでは反映されない）。

```bash
cd C:\Users\youhei_yoshikawa\projects\docker\lamp
docker compose up -d --build
```

`chat.local` を使うには、管理者権限で hosts に追記する。

```powershell
Add-Content C:\Windows\System32\drivers\etc\hosts "127.0.0.1 chat.local"
ipconfig /flushdns
```

### サブパス（/php-chat/）対応
当初はリダイレクトやリンクが `/login.php` のような絶対パスで、`/php-chat/` 配下に置くと `http://<host>/login.php` に飛んで 404 になった。次の対応でルート直下でもサブパスでも動く。

- `lib/bootstrap.php` に `base_path()` / `url()` を追加。`SCRIPT_NAME` から公開パスを自動判定する
- `Location:`、`href`、`action`、`chat.js` の読み込みを `url()` 経由に変更
- `chat.js` の `fetch('/translate.php')` は `window.CHAT.base` を前置（`chat.php` が `base` を渡す）
- セッション名を `chat_sid`、Cookie の path を公開パスに変更し、他プロジェクトの `PHPSESSID` と衝突しないようにした

新しく URL を書くときは、必ず `url('/xxx.php')` を使うこと。

### Socket.IO（next-chat-server）の設定
ブラウザが直接 `SOCKET_URL` に接続するため、他 PC から使うには次が必要。

`php-chat/.env`:
```
SOCKET_URL=http://172.16.2.25:3001
```

`next-chat-server/.env`:
```
SERVER_HOST=0.0.0.0
SERVER_PORT=3001
CORS_ORIGIN=http://localhost:3000,http://localhost:3001,http://chat.local,http://172.16.2.25
```

- `SERVER_HOST` が `localhost` / `chat.local` だと `127.0.0.1` でしか待たず、他 PC から `ERR_CONNECTION_REFUSED` になる
- CORS は Origin（スキーム + ホスト + ポート）の完全一致。アクセスに使う URL を追加する
- `.env` は起動時にしか読まれないので、変更後は **サーバーを再起動** する

### 他の PC から使うための Windows 設定
管理者権限の PowerShell で、受信 TCP 80 / 3001 を許可する（ネットワークが `yse.ac.jp`＝ドメインの場合は Domain プロファイル）。

```powershell
New-NetFirewallRule -DisplayName "chat http" -Direction Inbound -Protocol TCP -LocalPort 80,3001 -Action Allow -Profile Domain,Private
```

確認:

```powershell
netstat -ano | Select-String ':(80|3001)\s.*LISTENING'   # 3001 が 0.0.0.0 になっていること
Test-NetConnection 172.16.2.25 -Port 3001                  # 別 PC から
```

## 翻訳（Gemini）
`GEMINI_API_KEY` がダミー（`your_gemini_api_key`）や空だと、`translate.php` は `500 Missing API key` を返す。
Google AI Studio で発行したキーを `php-chat/.env` に設定する（PHP が毎回読むので再起動不要）。キーは Git にコミットしない。

## トラブルシューティング
| 症状 | 原因 / 対処 |
|---|---|
| `chat.local` が開かない | hosts に未追記。追記して `ipconfig /flushdns`。`https://` ではなく `http://` で開く |
| `localhost/...` から `/login.php` に飛んで 404 | 絶対パスが残っている。`url()` を使う |
| CORS エラー（`No 'Access-Control-Allow-Origin'`） | `next-chat-server/.env` の `CORS_ORIGIN` に Origin を追加し、再起動 |
| `ERR_CONNECTION_REFUSED`（:3001） | `SERVER_HOST` が `0.0.0.0` か、サーバーを再起動したか、ファイアウォールを確認 |
| 他 PC から開けない | ファイアウォール、学内ネットワークのクライアント分離、IP の変更（DHCP）を確認 |
| 翻訳が 500 | `GEMINI_API_KEY` が未設定 / ダミー |
| 設定を変えても反映されない | Apache は `docker compose up -d --build`、Socket.IO は再起動 |

## 注意
- IP が DHCP で変わると `SOCKET_URL` と `CORS_ORIGIN` が合わなくなる。変わったら 2 か所を直す
- HTTP のためパスワードは平文で流れる。学内限定でも、重要なパスワードを使い回さない
- HTTP の `localhost` 以外ではカメラ / マイクが使えないことがある（`next-chat` の VideoChat など）
- `/php-chat/` で公開される範囲は `public/` のみ。`lib/` と `.env` は DocumentRoot の外にあるが、`http://<host>/chat/php-chat/lib/` のように、Docker の DocumentRoot 全体からは到達できる。LAN に公開する場合は注意
