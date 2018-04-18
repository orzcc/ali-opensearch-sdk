# ali-opensearch-sdk

此包代码 Fork 来自 Lingxi: https://github.com/lingxi/ali-opensearch-sdk

建议常规应用使用 Lingxi 的原始包，本包进行了一部分自定义修改，未做过多通用场景的考虑。

原始包中需要在 Model 中添加 toSearchableDocCallbacks 方法以禁用关联更新，此包在配置文件中添加了一个开关，可全局禁用Model观察，采用OpenSearch自动与RDS同步的方案。


# 介绍

应用层，基于 laravel scout 实现：https://laravel.com/docs/5.6/scout#custom-engines

阿里云有开放搜索服务：https://help.aliyun.com/document_detail/29104.html

## 安装

```shell
composer require orzcc/ali-opensearch-sdk
```

## 配置

在你的 scout.php

```php
<?php

return [
    'driver' => 'opensearch',

    'prefix' => '', // 应用前缀

    'queue' => true, // 是否开启队列同步数据

    'opensearch' => [

        'access_key_id'     => env('OPENSEARCH_ACCESS_KEY'),

        'access_key_secret' => env('OPENSEARCH_ACCESS_SECRET'),

        'host'              => env('OPENSEARCH_HOST'),

        'debug'             => env('OPENSEARCH_DEBUG'),

    ],

    'searchable_enabled'=> false, // 关联更新到 Open Search（ false 代表全局禁用）

    'count' => [

        'unsearchable' => 20, // 一次性删除文档的 Model 数量

        'searchable' => 20, // 一次性同步文档的 Model 数量

        'updateSearchable' => 20, // 一次性更新(先删除，再更新)文档的 Model 数量

    ],
]
```

## 注册服务

```php
Laravel\Scout\ScoutServiceProvider::class,
Orzcc\AliOpenSearch\OpenSearchServiceProvider::class,
```

---

## 使用

请先阅读：https://laravel.com/docs/5.3/scout

在 Model 里添加 Searchable Trait：

```php
<?php

namespace App\Models;

use Orzcc\AliOpenSearch\Searchable;

class User extends Model
{
    use Searchable;

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'user_index';
    }

    public function getSearchableFields()
    {
        return [
            'id',
            'name'
        ];
    }
}
```

开始使用：

简单搜索

```php
<?php

$result = User::search(['name' => 'orzcc'])
    ->select([
        'id',
        'name',
        'age',
    ])
    ->filter(['age', '<', '30'])
    ->filter(['age', '>', '18'])
    ->orderBy('id', 'desc')
    ->paginate(15);
```

更为复杂的情况就是对搜索添加的构造，仿照 laravel model/builder 的思想写了一个对 Opensearch 的 查询构造器.

> 根据条件动态的搜索, 基本和 eloquent 提供的数据库查询保持一致.

```php
<?php

use Orzcc\AliOpenSearch\Query\QueryStructureBuilder as Query;

$q = $_GET['query'];

$query = Query::make()
    ->where(function ($query) use ($q) {
            return $query->where('name', $q)
        ->when(strpos($q, "@") !== false && $q != "@", function ($query) use ($q) {
            return $query->orWhere('email', $q);
        })
        ->when(is_numeric($q), function ($query) use ($q) {
            return $query->orWhere('mobile', $q);
        });
    });

$users = User::search($query)
    ->filter('age', 18)
    ->take(5)
    ->get();
```
