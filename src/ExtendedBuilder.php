<?php

namespace Orzcc\AliOpenSearch;

use Illuminate\Pagination\Paginator;
use Orzcc\AliOpenSearch\Helper\Whenable;
use Laravel\Scout\Builder as ScoutBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExtendedBuilder extends ScoutBuilder
{
    use Whenable;

    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * The query expression.
     *
     * @var mixed
     */
    public $query;

    /**
     * Optional callback before search execution.
     *
     * @var string
     */
    public $callback;

    /**
     * The custom index specified for the search.
     *
     * @var string
     */
    public $index;

    /**
     * The "where" constraints added to the query.
     *
     * @var array
     */
    public $filters = [];

    /**
     * The "order" that should be applied to the search.
     *
     * @var array
     */
    public $orders = [];

    /**
     * The "limit" that should be applied to the search.
     *
     * @var int
     */
    public $limit;

    /**
     * The current page. "start" in open search query.
     *
     * @var int
     */
    public $page;

    /**
     * Custom filter strings.
     *
     * @var array
     */
    public $rawFilters = [];

    /**
     * Custom query strings.
     *
     * @var array
     */
    public $rawQuerys = [];

    /**
     * Fetching fields from opensearch.
     *
     * @var array
     */
    public $fields = [];

    /**
     * Distinct 排序.
     *
     * @var array
     */
    public $distincts = [];

    /**
     * Aggregates 设定规则.
     *
     * @var array
     */
    public $aggregates = [];

    /**
     * rerankSize表示参与精排算分的文档个数，一般不用使用默认值就能满足，不用设置,会自动使用默认值200.
     * @var int
     */
    public $rerankSize = 200;

    /**
     * 指定kvpairs子句的内容，内容为k1:v1,k2:v2的方式表示.
     *
     * @var string
     */
    public $kvpair = '';

    /**
     * 指定qp 名称.
     * @var array
     */
    public $QPName = [];

    /**
     * 指定表达式名称，表达式名称和结构在网站中指定.
     *
     *
     * @var string
     */
    public $formulaName = '';

    /**
     * 指定粗排表达式名称，表达式名称和结构在网站中指定.
     * @var string
     */
    public $firstFormulaName = '';

    /**
     * 设定自定义参数.
     *
     * 如果api有新功能（参数）发布，用户不想更新sdk版本，则可以自己来添加自定义的参数.
     *
     * @var string
     */
    public $customParams = [];

    public $scrollId = null;

    public $scroll = null;

    /**
     * Create a new search builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $query
     * @param  Closure  $callback
     * @return void
     */
    public function __construct($model, $query, $callback = null)
    {
        $this->model = $model;
        $this->query = $query;
        $this->callback = $callback;

        $this->select();
    }

    /**
     * Specify a custom index to perform this search on.
     *
     * @param  string  $index
     * @return $this
     */
    public function within($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Add a constraint to the search filter.
     *
     * @param  mixed  $field
     * @param  mixed  $value
     * @return $this
     */
    public function filter($field, $value = null)
    {
        if (is_array($field)) {
            $this->filters[] = $field;
        } else {
            if (! is_array($value)) {
                $value = [$field, '=', $value];
            } else {
                array_unshift($value, $field);
            }

            $this->filters[] = $value;
        }

        return $this;
    }

    /**
     * Set the "limit" for the search query.
     *
     * @param  int  $limit
     * @return $this
     */
    public function take($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function forPage($page, $perPage = 20)
    {
        $this->page = $page;
        $this->limit = $perPage;

        return $this;
    }

    /**
     * Add a constraint to the search query.
     *
     * @param  string  $field
     * @param  array  $values
     * @return $this
     */
    public function filterIn($field, array $values = [])
    {
        $this->rawFilters[] = '(' . collect($values)->map(function($item) use ($field) {
            $item = !is_numeric($item) && is_string($item) ? '"' . $item . '"' : $item;
            return $field . '=' . $item;
        })->implode(' OR ') . ')';

        return $this;
    }

    /**
     * Add an "order" for the search query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) == 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * Add an default rank to order.
     *
     * @param  string  $direction
     * @return $this
     */
    public function orderByRank($direction = 'desc')
    {
        $this->orders[] = [
            'column' => 'RANK',
            'direction' => strtolower($direction) == 'desc' ? 'desc' : 'asc',
        ];

        return $this;
    }

    public function select($fields = null)
    {
        if (empty($fields)) {
            $fields = $this->model->getSearchableFields();

            if (! is_array($fields)) {
                $fields = explode(',', $fields);
            }
        }

        $this->fields = $fields;

        return $this;
    }

    public function filterRaw($rawFilter)
    {
        $this->rawFilters[] = $rawFilter;

        return $this;
    }

    public function searchRaw($rawQuery)
    {
        $this->rawQuerys[] = $rawQuery;

        return $this;
    }

    /**
     * 添加distinct排序信息
     *
     * 例如：检索关键词“手机”共获得10个结果，分别为：doc1，doc2，doc3，doc4，doc5，doc6，
     * doc7，doc8，doc9，doc10。其中前三个属于用户A，doc4-doc6属于用户B，剩余四个属于用户C。
     * 如果前端每页仅展示5个商品，则用户C将没有展示的机会。但是如果按照user_id进行抽取，每轮抽
     * 取1个，抽取2次，并保留抽取剩余的结果，则可以获得以下文档排列顺序：doc1、doc4、doc7、
     * doc2、doc5、doc8、doc3、doc6、doc9、doc10。可以看出，通过distinct排序，各个用户的
     * 商品都得到了展示机会，结果排序更趋于合理。
     * 更多说明请参见 [API distinct子句]({{!api-reference/query-clause&distinct-clause!}})
     *
     * @param string $key 为用户用于做distinct抽取的字段，该字段要求建立Attribute索引。
     * @param int $distCount 为一次抽取的document数量，默认值为1。
     * @param int $distTimes 为抽取的次数，默认值为1。
     * @param string $reserved 为是否保留抽取之后剩余的结果，true为保留，false则丢弃，丢弃时totalHits的个数会减去被distinct而丢弃的个数，但这个结果不一定准确，默认为true。
     * @param string $distFilter 为过滤条件，被过滤的doc不参与distinct，只在后面的 排序中，这些被过滤的doc将和被distinct出来的第一组doc一起参与排序。默认是全部参与distinct。
     * @param string $updateTotalHit 当reserved为false时，设置update_total_hit为true，则最终total_hit会减去被distinct丢弃的的数目（不一定准确），为false则不减；默认为false。
     * @param int $maxItemCount 设置计算distinct时最多保留的doc数目。
     * @param number $grade 指定档位划分阈值。
     */
    public function addDistinct()
    {
        $this->distincts[] = func_get_args();

        return $this;
    }

    /**
     * 添加统计信息相关参数
     *
     * 一个关键词通常能命中数以万计的文档，用户不太可能浏览所有文档来获取信息。而用户感兴趣的可
     * 能是一些统计类的信息，比如，查询“手机”这个关键词，想知道每个卖家所有商品中的最高价格。
     * 则可以按照卖家的user_id分组，统计每个小组中最大的price值：
     * groupKey:user_id, aggFun: max(price)
     * 更多说明请参见 [APi aggregate子句说明]({{!api-reference/query-clause&aggregate-clause!}})
     *
     * @param string $groupKey 指定的group key.
     * @param string $aggFun 指定的function。当前支持：count、max、min、sum。
     * @param string $range 指定统计范围。
     * @param string $maxGroup 最大组个数。
     * @param string $aggFilter 表示仅统计满足特定条件的文档。
     * @param string $aggSamplerThresHold 抽样统计的阈值。表示该值之前的文档会依次统计，该值之后的文档会进行抽样统计。
     * @param string $aggSamplerStep 抽样统计的步长。
     */
    public function addAggregate()
    {
        $this->aggregates[] = func_get_args();

        return $this;
    }

    /**
     * 指定精排算分的文档个数
     *
     * 若不指定则使用默认值200
     *
     * @param int $rerankSize 精排算分文档个数
     */
    public function addRerankSize($rerankSize)
    {
        $this->rerankSize = $rerankSize;

        return $this;
    }

    /**
     * 设置kvpair
     * 更多说明请参见 [API 自定义kvpair子句]({{!api-reference/query-clause&kvpair-clause!}})
     *
     * @param string $pair 指定的pair信息。
     */
    public function setPair($kvpair)
    {
        $this->kvpair = $kvpair;

        return $this;
    }

    /**
     * 添加一条查询分析规则
     *
     * @param QPName 查询分析规则
     */
    public function addQPName($QPName)
    {
        if (is_array($QPName)) {
            $this->QPName = $QPName;
        } else {
            $this->QPName[] = $QPName;
        }

        return $this;
    }

    /**
     * 设置表达式名称
     * 此表达式名称和结构需要在网站中已经设定。
     * @param string $formulaName 表达式名称。
     */
    public function setFormulaName($formulaName)
    {
        $this->formulaName = $formulaName;

        return $this;
    }

    /**
     * 清空精排表达式名称设置
     */
    public function clearFormulaName()
    {
        $this->formulaName = '';

        return $this;
    }

    /**
     * 设置粗排表达式名称
     *
     * 此表达式名称和结构需要在网站中已经设定。
     *
     * @param string $FormulaName 表达式名称。
     */
    public function setFirstFormulaName($formulaName)
    {
        $this->firstFormulaName = $formulaName;

        return $this;
    }

    /**
     * 增加自定义参数
     *
     * @param string $paramKey 参数名称。
     * @param string $paramValue 参数值。
     */
    public function addCustomParam($paramKey, $paramValue)
    {
        $this->customParams[$paramKey] = $paramValue;

        return $this;
    }

    /**
     * 设置scroll扫描起始id
     *
     * @param scrollId 扫描起始id
     */
    public function setScrollId($scrollId)
    {
        $this->scrollId = $scrollId;

        return $this;
    }

    /**
     * 设置此次获取的scroll id的期时间。
     *
     * 可以为整形数字，默认为毫秒。也可以用1m表示1min；支持的时间单位包括：
     * w=Week, d=Day, h=Hour, m=minute, s=second
     *
     * @param string|int $scroll
     */
    public function setScroll($scroll)
    {
        $this->scroll = $scroll;
    }

    /**
     * Get the keys of search results.
     *
     * @return \Illuminate\Support\Collection
     */
    public function keys()
    {
        return $this->engine()->keys($this);
    }

    /**
     * Get the first result from the search.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function first()
    {
        return $this->get()->first();
    }

    /**
     * Get the results of the search.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get()
    {
        return $this->engine()->get($this);
    }

    /**
     * Get the facet from aggregate.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function facet($key)
    {
        return $this->engine()->facet($key, $this);
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $this->forPage($page, $perPage);

        $results = Collection::make($engine->map(
            $rawResults = $engine->paginate($this, $perPage, $page), $this->model
        ));

        $paginator = (new LengthAwarePaginator($results, $engine->getTotalCount($rawResults), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
            'viewtotal' => $engine->getViewTotal($rawResults),
        ]));

        return $paginator->appends('query', $this->query);
    }

    /**
     * Get the engine that should handle the query.
     *
     * @return mixed
     */
    protected function engine()
    {
        return $this->model->searchableUsing();
    }
}
