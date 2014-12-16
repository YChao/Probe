     /**
     * [get_pre_items 获取预热活动的商品]
     * @param  [type] $activity_id  [专场活动idid]
     * @param  [type] $brand_limits [品牌特卖抓取的个数]
     * @param  [type] $start_time   [抓取商品的开始时间]
     * @param  [type] $end_time     [抓取商品的结束时间]
     * @return [object]             [最终的商品数据]
     */
    private function get_pre_items($activity_id, $brand_limits, $start_time, $end_time) {
        load_lib('cache_common:memcached_library');
        load_lib('search_common:CoreSeekClient');

        $region = 'today_tuan';
        $client = $this->coreSeekClient;
        $client->SetMatchMode(SPH_MATCH_FULLSCAN);
        $query = NULL;
        $client->ResetFilters();
        $client->setFilter('status', array(1));
        $client->setFilter('activity_id', array($activity_id));
        $client->SetSortMode(SPH_SORT_EXTENDED, 'start_time ASC');
        $client->SetLimits(0, 10);
        $results = $client->Query($query, 'tuan_main');
        $list = isset($results['matches']) ? $results['matches'] : array();
        $miaoshai_items = convert_tuan_item_list($list);
        $miaoshai_items = array_slice($miaoshai_items, 0, 8);
        $this->context->put('miaoshai_items', $miaoshai_items);

        $h = date('H', $_SERVER['REQUEST_TIME']);
        $beginning = $h * 8;
        $key = 'cxk_cache';
        $cxk_cache = $this->memcached_library->get($region, $key);

        if (!$cxk_cache || !isset($cxk_cache['items']) || !isset($cxk_cache['build_time']) || ($_SERVER["REQUEST_TIME"] - $cxk_cache['build_time']) > 600 || _get('throw_cache')) {
            //10元购
            $client->ResetFilters();
            $client->SetMatchMode(SPH_MATCH_FULLSCAN);
            $query = NULL;
            $client->setFilterRange('price', 100, 1999);
            $client->setFilter('status', array(1, 2));
            $client->setFilter('activity_id', array(106, 99), TRUE);
            $client->setFilterRange('start_time', $start_time, $end_time);
            $client->SetSortMode(SPH_SORT_EXTENDED, 'sort ASC');
            $client->SetLimits($beginning, 10);
            $client->AddQuery($query, 'tuan_main');

            //优品惠
            $client->ResetFilters();
            $client->SetMatchMode(SPH_MATCH_FULLSCAN);
            $query = NULL;
            $client->setFilterRange('price', 2000, 10000);
            $client->setFilter('status', array(1, 2));
            $client->setFilterRange('start_time', $start_time, $end_time);
            $client->SetSortMode(SPH_SORT_EXTENDED, 'sort ASC');
            $client->SetLimits($beginning, 10);
            $client->AddQuery($query, 'tuan_main');

            //品牌团
            $client->ResetFilters();
            $client->SetMatchMode(SPH_MATCH_FULLSCAN);
            $query = NULL;
            $client->setFilter('status', array(1));
            $client->setFilterRange('start_time', $start_time, $end_time);
            $client->SetSortMode(SPH_SORT_EXTENDED, 'sort ASC');
            $client->SetLimits($beginning, 10);
            $client->AddQuery($query, 'brand_main');

            //后备数据
            $client->ResetFilters();
            $client->SetMatchMode(SPH_MATCH_FULLSCAN);
            $query = NULL;
            $client->setFilterRange('price', 2000, 10000);
            $client->setFilter('status', array(1, 2));
            $client->setFilterRange('start_time', $start_time, $end_time);
            $client->SetSortMode(SPH_SORT_EXTENDED, 'sort ASC');
            $client->SetLimits(0, 40);
            $client->AddQuery($query, 'tuan_main');

            $results = $client->RunQueries();

            //10yaungou数据
            $list = isset($results[0]['matches']) ? $results[0]['matches'] : array();
            $shiyuan_items = convert_tuan_item_list($list);
            $shiyuan_items = array_slice($shiyuan_items, 0, 8);

            //优品惠
            $list = isset($results[1]['matches']) ? $results[1]['matches'] : array();
            $quility_items = convert_tuan_item_list($list);
            $quility_items = array_slice($quility_items, 0, 8);

            //品牌团
            $brand_list = isset($results[2]['matches']) ? $results[2]['matches'] : array();
            $brand_items = brand_draw_items($brand_list, 1);
            $brand_items = array_slice($brand_items, 0, 8);

            $cxk_items = array();
            while (TRUE) {
                if (!$brand_items && !$quility_items && !$shiyuan_items) {
                    break;
                }

                if ($brand_items) {
                    array_push($cxk_items, array_shift($brand_items));
                }

                if ($quility_items) {
                    array_push($cxk_items, array_shift($quility_items));
                }

                if ($shiyuan_items) {
                    array_push($cxk_items, array_shift($shiyuan_items));
                }
            }

            if (count($cxk_items) < 24) {
                //品牌团
                $nb_list = isset($results[3]['matches']) ? $results[3]['matches'] : array();
                $hb_items = convert_tuan_item_list($nb_list);
                $cxk_items = array_merge($cxk_items, $hb_items);
            }

            $cxk_itmes = array_slice($cxk_items, 0, 24);
            $this->memcached_library->put($region, $key, array('items' => $cxk_itmes, 'build_time' => $_SERVER["REQUEST_TIME"]));
        } else {
            $cxk_items = $cxk_cache['items'];
        }
    }
