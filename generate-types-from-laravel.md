# Laravel DataとTypeScript Transformerによる型定義の自動生成ガイド

この記事で紹介されている `Laravel Data` と `TypeScript Transformer` を利用して、LaravelのPHPコードからTypeScriptの型定義を自動生成する手順と、そのプロセスを効率化するためのプロンプトを以下にまとめます。

## 概要

この手法は、`spatie/laravel-data` を使ってPHPクラスとしてデータ構造を定義し、`spatie/laravel-typescript-transformer` を使ってそのPHPクラスからTypeScriptの型定義ファイルを自動生成するものです。
これにより、フロントエンドとバックエンド間でのデータ構造の同期が容易になり、型安全な開発を実現できます。

## 手順

### 1. 必要なパッケージのインストール

まず、`composer` を使用して、必要なPHPパッケージをインストールします。

```bash
composer require spatie/laravel-data spatie/laravel-typescript-transformer
```

### 2. 設定ファイルの公開

次に、`TypeScript Transformer` の設定ファイルを公開します。

```bash
php artisan vendor:publish --tag=typescript-transformer-config
```

これにより、`config/typescript-transformer.php` が作成されます。

### 3. 設定の変更 (`config/typescript-transformer.php`)

公開された設定ファイルで、`auto_discover_types` に `Data` クラスが置かれているディレクトリを指定します。
例えば、`app/Data` ディレクトリに `Data` クラスを配置する場合、以下のように設定します。

```php
'auto_discover_types' => [
    app_path('Data'),
],
```

また、出力先のディレクトリなどもこのファイルで設定可能です。

### 4. `Data` クラスの作成

`app/Data` ディレクトリ（または設定した他のディレクトリ）に、TypeScriptに変換したいデータ構造を持つ `Data` クラスを作成します。

**例: `app/Data/SongData.php`**

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SongData extends Data
{
    public function __construct(
        public string $title,
        public string $artist,
    ) {
    }
}
```

### 5. 型定義の生成コマンドの実行

以下の `artisan` コマンドを実行すると、`Data` クラスからTypeScriptの型定義が生成されます。

```bash
php artisan typescript:transform
```

成功すると、設定ファイルで指定された出力先（デフォルトでは `resources/js/types/generated.d.ts`）にファイルが生成されます。

**生成されるTypeScriptの例:**

```typescript
// resources/js/types/generated.d.ts
export type SongData = {
    title: string;
    artist: string;
};
```

### 6. フロントエンドでの利用

生成された型をフロントエンドのコードでインポートして利用します。

```typescript
import { SongData } from '@/types/generated';

const song: SongData = {
    title: 'Yesterday',
    artist: 'The Beatles',
};
```

---

## この作業を効率化するためのプロンプト案

以下に、この一連の作業をAIアシスタントに依頼するためのプロンプトの例を示します。

### プロンプト例

「
**依頼:**

Laravelプロジェクトにおいて、`spatie/laravel-data` と `spatie/laravel-typescript-transformer` を利用して、PHPの `Data` クラスからTypeScriptの型定義を自動生成する設定と実装を行ってください。

**詳細手順:**

1.  以下の `composer` パッケージが未インストールの場合はインストールしてください。
    *   `spatie/laravel-data`
    *   `spatie/laravel-typescript-transformer`

2.  `php artisan vendor:publish --tag=typescript-transformer-config` を実行して、設定ファイルを公開してください。

3.  `config/typescript-transformer.php` を開き、`auto_discover_types` に `app/Data` ディレクトリを追加してください。

4.  以下の内容で、`app/Data/ExampleData.php` というサンプル `Data` クラスを作成してください。

    ```php
    <?php

    namespace App\Data;

    use Spatie\LaravelData\Data;

    class ExampleData extends Data
    {
        public function __construct(
            public int $id,
            public string $name,
            public bool $is_active,
        ) {
        }
    }
    ```

5.  `php artisan typescript:transform` コマンドを実行して、TypeScriptの型定義を生成してください。

6.  生成されたファイル（`resources/js/types/generated.d.ts`）の内容を確認し、`ExampleData` の型が正しくエクスポートされていることを報告してください。
」
