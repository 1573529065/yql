<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/12/10
 * Time: 14:21
 */

namespace App\Unit;

use Elasticsearch\ClientBuilder as Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception as Exception;
use Elasticsearch\Endpoints\Info;
use Illuminate\Support\Facades\Log;

class Elasticsearch
{
    protected $client;

    protected $hosts;

    public function __construct()
    {
        $this->hosts[] = env('ELASTIC_SEARCH_HOST');

        $this->client = Client::create()->setHosts($this->hosts)->build();

    }

    /**
     * 创建索引
     * @param $index_name
     * @return array|\Illuminate\Http\JsonResponse
     */

    public function create_index(string $index_name, array $mappings)
    {
        $params = [
            'index' => $index_name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 5,
                    'number_of_replicas' => 0
                ],
                'mappings' => $mappings
            ]
        ];

        try {
            return $this->client->indices()->create($params);
        } catch (Exception $e) {
            return response_json(300, Json::decode($e->getMessage(), true));
        }
    }

    /**
     * 删除索引
     * @param $index_name
     * @return array|\Illuminate\Http\JsonResponse
     */

    public function delete_index(string $index_name)
    {
        $params = [
            'index' => $index_name
        ];

        try {
            return $this->client->indices()->delete($params);
        } catch (Exception $e) {
            return response_json(300, Json::decode($e->getMessage(), true));
        }
    }

    /**
     * 添加映射模版
     * @param string $type_name
     * @param string $index_name
     * @return array
     */

    public function put_mappings(string $type_name, string $index_name)
    {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'body' => [
                $type_name => [
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => [
                        'id' => [
                            'type' => 'integer', // 整型
                            'index' => 'not_analyzed',
                        ],
                        'title' => [
                            'type' => 'string', // 字符串型
                            'index' => 'analyzed', // 全文搜索
                            'analyzer' => 'ik_max_word'
                        ],
                        'content' => [
                            'type' => 'string',
                            'index' => 'analyzed',
                            'analyzer' => 'ik_max_word'
                        ],
                        'price' => [
                            'type' => 'integer'
                        ]
                    ]
                ]
            ]
        ];
        try {
            return $this->client->indices()->putMapping($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * 查看映射
     * @param string $type_name
     * @param string $index_name
     * @return \Illuminate\Http\JsonResponse
     */

    public function get_mapping(string $type_name, string $index_name)
    {
        $params = [
            'index' => $index_name,
            'type' => $type_name
        ];

        try {

            return $this->client->indices()->getMapping($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * 添加文档
     * @param int $id
     * @param array $doc
     * @param string $index_name
     * @param string $type_name
     * @return \Illuminate\Http\JsonResponse
     */

    public function add_doc(int $id, array $doc, string $index_name, string $type_name)
    {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id,
            'body' => $doc
        ];

        try {
            return $this->client->index($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * 判断文档是否存在
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return \Illuminate\Http\JsonResponse
     */

    public function exists_doc(int $id, string $index_name, string $type_name)
    {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id
        ];

        try {
            return $this->client->exists($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * 获取文档
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return \Illuminate\Http\JsonResponse
     */

    public function get_doc(int $id, string $index_name, string $type_name)
    {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id
        ];

        try {
            return $this->client->get($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * 更新文档
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return array
     */

    public function update_doc(int $id, array $body, string $index_name, string $type_name)
    {
        // 可以灵活添加新字段,最好不要乱添加
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id,
            'body' => [
                'doc' => $body
//                    [
//                    'title' => '苹果手机iPhoneX'
//                ]
            ]
        ];

        try {
            return $this->client->update($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * @return \Elasticsearch\Client
     */
    public function getClient(): \Elasticsearch\Client
    {
        return $this->client;
    }

    /**
     * 删除文档
     * @param int $id
     * @param string $index_name
     * @param string $type_name
     * @return \Illuminate\Http\JsonResponse
     */

    public function delete_doc(int $id = 1, string $index_name, string $type_name)
    {
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'id' => $id
        ];

        try {
            return $this->client->delete($params);
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));

            return false;
        }
    }

    /**
     * 查询文档 (分页，排序，权重，过滤)
     * @param array $query
     * @param string $index_name
     * @param string $type_name
     * @param int $from
     * @param int $size
     * @param array $other
     * @param int $type
     * @return array
     */
    public function search_doc(array $query, string $index_name, string $type_name, int $from = 0, int $size = 20, array $other = [], int $type = 1)
    {
        $query = $this->handleQuery($query, $type);
        $params = [
            'index' => $index_name,
            'type' => $type_name,
            'body' => [
                'query' => $query,
                'from' => $from, 'size' => $size
            ]
        ];
        $other && $params = array_merge_recursive($params, $other);
        try {
            $ret = $this->client->search($params);
            return $ret ? ['total' => $ret['hits']['total'], 'data' => array_column($ret['hits']['hits'], '_source')] : ['total' => 0, 'data' => []];
        } catch (Exception $e) {
            Log::debug('es_error', Json::decode($e->getMessage(), true));
            return ['total' => 0, 'data' => []];
        }
    }

    /**
     * 根据类型组装query数据
     * @param $query
     * @param $type 多种类型  可自行补充
     * @return array
     */
    private function handleQuery($query, $type)
    {
        switch ($type) {
            case 1://bool
                $query = [
                    'bool' => $query,
                ];
                break;
            case 2://普通
                //$query = ["match" => ["ub_nickname" =>["query" => "可乐"]]];
                break;
            default:
                $query = [
                    'bool' => [
                        'should' => $query,
                    ],
                ];
                break;
        }

        return $query;
    }

    public function test()
    {
        $params = [
            'index' => 'my_index',
            'type' => 'my_type',
            'id' => '1',
            'body' => ['testField' => 'abc']
        ];
        $response = $this->client->index($params);//添加索引
        return json_encode($response);
    }

    public function t()
    {
        $params = [
            'index' => 'my_index',
            'type' => 'my_type',
            'id' => 1
        ];
        return $this->client->get($params);
    }

    /**
     * @param array $arr
     * @return string
     */
    public static function buildSort(array $arr): string
    {
        $sort = [];

        foreach ($arr as $k => $v) {
            $toPush = is_integer($k) ? $v : $k . ':' . $v;
            array_push($sort, $toPush);
        }
        return implode(',', $sort);
    }

}