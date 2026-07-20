# サーバーサイドAPI疎結合化リファクタリング

## 背景

moreplaylist のサーバーサイド（`src/Controller/VideoController.php` 中心）は、
リポジトリ所有者が「かなり苦労して今の状態になっている」「あまり疎結合にできていない」
と課題視している部分。本番稼働中のアプリであり、かつ以前クライアントサイドJSの複雑さで
実際にバグを踏んだ経験から、コードを大きく触ることへの不安がある
（`docs/LOCAL_DEV_SETUP.md` 作成時のセッション、および先行するモバイルUI改修セッションの
文脈を参照）。そのため本リファクタリングは、**本番を壊さないことを最優先に、小さく検証
可能なフェーズへ意図的に細分化**して進める方針を取っている。

安全網としては以下が使える：
- `phpunit.xml.dist` によるPHPUnitテスト（本リファクタリング着手時点では
  静的URL解析ヘルパーの4アサーションのみ、YouTube API/セッション認証まわりは無カバー
  だった。Phase 0で拡充した — 後述）
- 本番同等のローカルWSL2環境（`https://localhost:8443/`、実OAuth + 実YouTube Data APIキー、
  セットアップ手順は `docs/LOCAL_DEV_SETUP.md`）。テストカバレッジが薄いため、
  **`curl` での実エンドポイント叩き比べ（変更前後のレスポンス差分）が主要な検証手段**。

## 発見した重要な事実（設計に直結するもの）

1. **`/api/videos`（`VideoController::getVideos`）は認証を通らない唯一のYouTube呼び出し
   エンドポイント。** developer keyのみで動く公開API。他の `getPlaylists`/
   `getPlaylistVideos`/`addPlaylist`/`addToExistingPlaylist` は全てOAuthセッション必須。

2. **一見「重複」に見えるデータ整形ブロックが4箇所あるが、実は仕様が微妙に違う：**

   | 呼び出し元 | fields | maxResults | thumbnail | エスケープ | 削除動画フィルタ | エラー時 |
   |---|---|---|---|---|---|---|
   | `getVideos()` feed_url分岐 | `snippet` | 10 | `default` | なし | なし | 500 JSON |
   | `getVideos()` keyword分岐 | `snippet` | 10 | `default` | なし | なし | 500 JSON |
   | `getPlaylistVideos()`（認証必須） | `id,snippet` | 20 | `medium` | `htmlspecialchars` | あり | 500 JSON |
   | 静的 `getVideosByKeyword()`（SSR用） | `id,snippet` | 20 | `medium` | なし | なし | 握り潰して`[]` |
   | 静的 `getVideosFromPlaylist()`（SSR用） | `id,snippet` | 20 | `default` | なし | なし | 握り潰して空構造 |

   **単純にDRYで1つに統合すると壊れる。** サービス層へ移す際は各呼び出し元の挙動を
   1対1でそのまま移植すること。

3. **`IndexController::handleRootAndIndex()` が計算する `$videos` は
   `application/views/index.phtml` のどこからも参照されていない**（テンプレート全文grep
   で確認済み）。ページ読み込みのたびに無駄にYouTube APIを叩いてクォータを消費している
   死んだコードだが、**今回のリファクタリングのスコープでは修正しない**（挙動を変えない
   ことを優先）。実際、Phase 0のベースライン取得で `?feed_url=<正常URL>` /
   `?feed_url=<不正URL>` / `?keyword=X` の3パターンのレンダリング結果がバイト完全一致する
   ことを確認済み（md5同一）。

4. **Google Clientの生成経路が実質4箇所独立している：**
   - `VideoController::__construct`（developer key、既存セッション利用）
   - 静的 `getVideosByKeyword()`（同上、SSR用）
   - 静的 `getVideosFromPlaylist()`（同上、SSR用）
   - DIコンテナの `googleClient`（`public/index.php` 定義、developer keyなし、
     `/Index/oauth` のOAuthハンドシェイク起点専用 — トークンがまだ無い状態向けなので
     **用途が本質的に異なり統合対象外**）
   上3つ（同じ用途）が統合対象。

5. **`/csrf-token` はトークンを発行するがPOST系エンドポイント
   （`add-playlist`/`add-to-existing-playlist`）で検証されていない。** 既存のセキュリティ
   ギャップ、今回のスコープ外。ユーザーへの報告のみ済み、修正は未着手。

6. **`RpcDispatcher::dispatch()` の入力バリデーション部分（不正JSON/`jsonrpc`不一致/
   `method`型不正/`params`型不正/未知メソッド）は `VideoController` を生成する前に完結し、
   `authenticateClient()` もセッショントークンが無ければ即 `false` を返す** —
   ネットワークアクセス無しでオフラインテスト可能（Phase 0で実装済み、後述）。

7. `IndexController` のデフォルトキーワードは `'lo fi jazz'`、`VideoController::getVideos()`
   側は `'Lo-Fi'` — 2つの異なるデフォルト値が同じ機能に存在する。既存の挙動として温存、
   修正しない。

## フェーズ全体計画

- **Phase 0 — 安全網構築**（✅ 完了）: curlベースライン記録 + オフラインPHPUnitテスト追加。
- **Phase 1 — URL解析の重複統合**（✅ 完了）: `src/Service/YoutubeVideoService.php` 新設、
  `extractPlaylistIdFromFeedUrl()` のみ実装。最小リスクのウォームアップ。
- **Phase 2 — Google Client生成・認証の統一**（⬜ 未着手、**最高リスク**）: 認証必須の
  全エンドポイントに影響する。トークンリフレッシュ経路の自動テストが無く、
  「ログイン直後は動くが、トークン期限切れ後に初めて壊れる」という一番厄介な失敗モードを
  持つため、単独フェーズとして切り出してある。詳細は下記「Phase 2 着手前に」を参照。
- **Phase 3 — データ整形ロジックの移動**（⬜ 未着手、a〜dに分割）:
  - 3a: `/api/videos`（未認証、feed_url/keyword分岐）
  - 3b: `IndexController` のSSRパス（`getVideosByKeyword`/`getVideosFromPlaylist`を置換、
    エラー握り潰しの挙動もそのまま移植）
  - 3c: `getPlaylistVideos()`（認証必須、`htmlspecialchars`と削除動画フィルタを保持）
  - 3d: `addPlaylist`/`addToExistingPlaylist`（実YouTubeアカウントへの書き込みを伴うため
    自動curl検証ではなく使い捨てプレイリストでの手動確認）
- **Phase 4 — `RpcDispatcher` の薄いアダプタ化**（⬜ 未着手）: `VideoController` ではなく
  `YoutubeVideoService` を直接使うよう変更。テスト用にサービスをinjectableにする。
- **Phase 5（範囲外、参考のみ）**: `routes.php` の `new VideoController(...)` 重複を
  DIコンテナのオートワイヤリングで削減、REST/JSON-RPC二重APIサーフェスの整理。
  いずれも今回のリファクタリングの目的（結合度低減）に直結しないため対象外。

## 現在の進捗（Phase 0〜1 完了時点）

### 新規/変更ファイル
- **新規** `src/Service/YoutubeVideoService.php` — コンストラクタは
  `__construct(SessionManager $session, LogManager $logManager)`。現時点の実装は
  `extractPlaylistIdFromFeedUrl(?string $feedUrl): ?string` のみ。Google Client・
  YouTube APIコールはまだ一切持たない（Phase 2以降で追加）。
- `src/Controller/VideoController.php` — `extractPlaylistId()`（private）と
  `getPlaylistIdFromUrl()`（public static）を削除。コンストラクタで
  `$this->videoService = new YoutubeVideoService($session, $logManager);` を保持するよう
  変更済み（Phase 2以降がこのプロパティに乗せていく前提の器）。`getVideos()` 内の
  呼び出しを `$this->videoService->extractPlaylistIdFromFeedUrl($feedUrl)` に変更。
  それ以外のメソッド（`getVideosByKeyword`/`getVideosFromPlaylist`含む）は無変更。
- `src/Controller/IndexController.php` — 同様に `$this->videoService` を保持、
  `VideoController::getPlaylistIdFromUrl()` 呼び出しを置換。
  `VideoController::getVideosByKeyword()`/`getVideosFromPlaylist()` の呼び出しは
  **まだ無変更**（Phase 3bの対象）。
- **削除** `tests/server/VideoControllerTest.php` → **新規**
  `tests/server/YoutubeVideoServiceTest.php` に同等+追加のエッジケーステストを移設
  （不正URL、`list=`+他パラメータ、空文字列、null）。
- **新規** `tests/server/RpcDispatcherTest.php` — オフラインで完結する分岐を全てカバー
  （不正JSON→`40000`、`jsonrpc`不一致→`40000`、`method`型不正→`40000`、
  `params`型不正→`40001`、未知メソッド→`40004`、未ログイン時の`auth.status`/
  `playlist.list`）。
- `phpunit.xml.dist` — `<php><ini name="error_log" .../></php>` を追加
  （下記「テスト時のハマりどころ」参照）。

### 見つけて直した副次的なバグ（挙動は変えていない、警告/非推奨の解消のみ）
1. `parse_url($feedUrl, PHP_URL_QUERY)` が `null` を返すケースで
   `parse_str(null, ...)` を呼んでいて PHP 8.3 の非推奨警告が出ていた
   （空文字列・不正URLのテストで新規に発覚）。`YoutubeVideoService` 側で
   `parse_url(...) ?? ''` を渡すよう修正。出力結果（`null`が返る）自体は変わらない。

### テスト時のハマりどころ（Phase 2以降でも再発するので記録）
- `LogManager` は Monolog の `ErrorLogHandler::OPERATING_SYSTEM` 経由で PHP の
  `error_log()` を呼ぶが、CLI SAPI かつ `error_log` ini が未設定だと標準エラー出力に
  書き込む。PHPUnitはテスト中の「予期しない出力」を警告扱いにし、`failOnWarning="true"`
  と合わさってテストが失敗する。`phpunit.xml.dist` の `<php><ini name="error_log".../>`
  で回避済み（スイート全体に効くので、新しいテストクラスでは何もしなくてよい）。
- `VideoController` を生成すると、コンストラクタが `GoogleClientFactory` 経由で
  `$_SERVER['HTTP_HOST']` を無条件参照する（`src/Utils/GoogleClientFactory.php:19`）。
  実リクエストでは必ず存在するが、PHPUnitのCLI実行では未設定で
  `Undefined array key "HTTP_HOST"` 警告が出る。`RpcDispatcherTest` では
  `setUp()` で `$_SERVER['HTTP_HOST'] = 'localhost';` を設定して回避（本番コードは
  触っていない — この関数自体はPhase 2で統一対象になるので、そのときに
  properな対処を検討する）。
- `SessionManager` は素のPHPセッション（`session_start()`）を使うグローバル状態なので、
  セッションに触れるテストは `#[RunInSeparateProcess]` を必ず付ける
  （既存の `SessionManagerTest.php` のパターンを踏襲）。

### 検証結果
- `php composer.phar exec phpunit` — 15 tests, 24 assertions, 全件パス、警告/非推奨0件。
- `npm test` — 10 tests、全件パス（このリファクタリングはJS/CSSに一切触れていない）。
- ローカル環境 `https://localhost:8443` に対するcurl差分検証 — 変更前後で全エンドポイントの
  ステータスコード・レスポンスボディが一致（`/api/videos` のキーワード検索結果のみ
  YouTube側のライブ検索結果が時間経過で変わるため差分が出たが、JSON構造は同一で
  コード変更とは無関係と確認済み）。

## Phase 2 着手前に確認すべきこと（次にこの続きをやるとき）

1. **ローカル環境がまだ使えるか確認する。** `systemctl is-active apache2`、
   `curl -sk https://localhost:8443/` で302が返るか。動かなくなっていたら
   `docs/LOCAL_DEV_SETUP.md` の手順で復旧。
2. **curlベースラインを取り直す。** 今回のスクラッチパス配下の記録
   （`/tmp/.../scratchpad/api-baseline/`、`api-after/`）は揮発性の一時ディレクトリにあり
   セッションを跨いで残っている保証がない。同じ手順（このドキュメントの
   「発見した重要な事実」の表と各エンドポイント一覧）で新規に取得すること。
3. **ログイン済みセッションでのベースラインが必要になる。** Phase 2は認証必須の全エンドポ
   イント（`getPlaylists`/`getPlaylistVideos`/`addPlaylist`/`addToExistingPlaylist`、
   および `RpcDispatcher` の `auth.status`/`playlist.list`）に影響するため、
   ブラウザで一度ログインして `PHPSESSID` を取得し、`curl -k -H "Cookie: PHPSESSID=..."`
   で認証必須ルートのベースラインを取ってから着手すること（Phase 0〜1では未実施）。
4. **トークンリフレッシュ経路の検証時間を確保する。** `authenticateClient()` の
   アクセストークン期限切れ→リフレッシュトークンでの再取得、という分岐は自動テストが
   無い。ログイン済みのローカルセッションを意図的に放置してトークンが失効するのを待ち、
   `logs/` に「Access token expired」→「New access token obtained」のログが出て
   かつAPIが引き続き200を返すことを確認するのが最も確実（YouTubeのトークンTTLはおよそ
   1時間、他の作業と並行して待つとよい）。
5. **ユーザーに一言確認してから着手する。** Phase 2は本ドキュメントの中で最もリスクが
   高いフェーズだと明示している。着手前にリスクを改めて説明し、了承を得ること
   （このリファクタリング全体が「ユーザーは全体像を見てから、どこまで進めるかを
   都度決めたい」という進め方で合意している）。

## 変更していないもの（このリファクタリングのスコープ外、既知の課題として記録のみ）
- `$videos` 未使用問題（IndexControllerのSSRが無駄にAPIを叩いている）
- `'Lo-Fi'` / `'lo fi jazz'` のデフォルトキーワード不一致
- CSRFトークン未検証
- REST（`/api/*`）とJSON-RPC（`/api/rpc`）が並存している二重APIサーフェス
- `routes.php` の `new VideoController(...)` 重複（DIオートワイヤリング未使用）
