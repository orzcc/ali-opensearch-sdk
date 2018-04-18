<?php

namespace Orzcc\AliOpenSearch\Query;

use Illuminate\Support\Facades\Config;
use Orzcc\AliOpenSearch\Sdk\CloudsearchSearch;

/**
 * laravel eloquent builder scheme to opensearch scheme
 */
class Builder
{
    protected $cloudsearchSearch;

    public function __construct(CloudsearchSearch $cloudsearchSearch)
    {
        $this->cloudsearchSearch = $cloudsearchSearch;
    }

    public function build($builder)
    {
        $this->index($builder->index ?: $builder->model->searchableAs());
        $this->query($builder->query, $builder->rawQuerys);
        $this->filter($builder->filters, $builder->rawFilters);
        $this->hit($builder->limit ?: 20, $builder->page ?: 1);
        $this->sort($builder->orders);
        $this->addFields($builder->fields);
        $this->addDistinct($builder->distincts);
        $this->addAggregate($builder->aggregates);
        $this->setPair($builder->pair);
        $this->addQPName($builder->QPName);
        $this->setFormulaName($builder->formulaName);

        $this->cloudsearchSearch->setFormat('json');

        return $this->cloudsearchSearch;
    }

    /**
     * 搜索的应用
     *
     * @param  array|string $index
     * @return null
     */
    protected function index($index)
    {
        if (is_array($index)) {
            foreach ($index as $key => $value) {
                $this->cloudsearchSearch->addIndex($value);
            }
        } else {
            $this->cloudsearchSearch->addIndex($index);
        }
    }

    /**
     * 过滤 filter 子句
     *
     * @see https://help.aliyun.com/document_detail/29158.html
     * @param  array $filters
     * @param  array $rawFilters
     * @return null
     */
    protected function filter(array $filters, array $rawFilters)
    {
        foreach ($filters as $filter) {
            list($key, $operator, $value) = $filter;

            if (!is_numeric($value) && is_string($value)) {
                // literal类型的字段值必须要加双引号，支持所有的关系运算，不支持算术运算
                $value = '"' . $value . '"';
            }

            $this->cloudsearchSearch->addFilter($key . $operator . $value, 'AND');
        }

        foreach ($rawFilters as $key => $value) {
            $this->cloudsearchSearch->addFilter($value, 'AND');
        }
    }

    /**
     * 查询 query 子句
     *
     * @example (name:'rry' AND age:'10') OR (name: 'lirui')
     *
     * @see https://help.aliyun.com/document_detail/29157.html
     * @param  mixed $query
     * @return null
     */
    protected function query($query, $rawQuerys)
    {
        if ($query instanceof QueryStructureBuilder) {
            $query = $query->toSql();
        } elseif (! is_string($query)) {
            $query = collect($query)
                ->map(function ($value, $key) {
                    return $key . ':\'' . $value . '\'';
                })
                ->implode(' AND ');
        }

        $query = $rawQuerys ? $query . ' AND ' . implode($rawQuerys, ' AND ') : $query;

        $this->cloudsearchSearch->setQueryString($query);
    }

    /**
     * 返回文档的最大数量
     *
     * @see https://help.aliyun.com/document_detail/29156.html
     * @param  integer $limit
     * @return null
     */
    protected function hit($limit, $page)
    {
        $this->cloudsearchSearch->setHits($limit);
        $this->cloudsearchSearch->setStartHit(($page - 1) * $limit);
    }

    /**
     * 排序sort子句
     *
     * @see https://help.aliyun.com/document_detail/29159.html
     * @param  array $orders
     * @return null
     */
    protected function sort(array $orders)
    {
        foreach ($orders as $key => $value) {
            $this->cloudsearchSearch->addSort($value['column'], $value['direction'] == 'asc' ? CloudsearchSearch::SORT_INCREASE : CloudsearchSearch::SORT_DECREASE);
        }
    }

    protected function addFields($fields)
    {
        $this->cloudsearchSearch->addFetchFields($fields);
    }

    protected function addDistinct($distincts)
    {
        foreach ($distincts as $distinct) {
            $this->cloudsearchSearch->addDistinct(...$distinct);
        }
    }

    protected function addAggregate($aggregates)
    {
        foreach ($aggregates as $aggregate) {
            $this->cloudsearchSearch->addAggregate(...$aggregate);
        }
    }

    protected function setPair($pair)
    {
        if ($pair) {
            $this->cloudsearchSearch->setPair($pair);
        }
    }

    protected function addQPName($QPName)
    {
        if ($QPName) {
            $this->cloudsearchSearch->addQPName($QPName);
        }
    }

    protected function setFormulaName($formulaName)
    {
        if ($formulaName) {
            $this->cloudsearchSearch->setFormulaName($formulaName);
        }
    }
}
