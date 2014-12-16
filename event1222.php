c function event1222() {
        $this->do_cache();
        $this->load->helper('tuan_helper');

        load_lib('cache_common:memcached_library');
        $region = 'today_tuan';
        $this->forward_to("event/e1222");

        

        $pre_time = strtotime('2014-12-24 00:00:00');
        $pre_end_time = $pre_time + (2 * 86400);
        $future_time = strtotime('2033-8-9 0:0:0');
        $h = date('H', $_SERVER['REQUEST_TIME']);

        $date = date("Ymd", $_SERVER['REQUEST_TIME']);

        // $uid = is_login() ? current_uid() : 0; 待定
        
    }

    /**
     * [get_pre_items 获取预热活动的商品]
     * @param  [type] $activity_id  [description]
     * @param  [type] $brand_limits [description]
     * @param  [type] $tuan_limits  [description]
     * @param  [type] $you_limits   [description]
     * @param  [type] $start_time   [description]
     * @param  [type] $end_time     [description]
     * @return [type]               [description]
     */
    private function get_pre_items($activity_id, $brand_limits, $tuan_limits, $you_limits, $start_time, $end_time) {
        load_lib('search_common:CoreSeekClient');
        $client = $this->coreSeekClient;
        $client->SetMatchMode(SPH_MATCH_FULLSCAN);
        $query = NULL;
        $client->ResetFilters();
        $client->setFilter('status', array(1));
        $client->setFilter('activity_id', array(99, 106, 108, 109));
        $client->SetSortMode(SPH_SORT_EXTENDED, 'start_time ASC');
        $client->SetLimits(0, 10);
        $results = $client->Query($query, 'tuan_main');
        $list = isset($results['matches']) ? $results['matches'] : array();
        $miaoshai_items = convert_tuan_item_list($list);
        $miaoshai_items = array_slice($miaoshai_items, 0, 8);
        $this->context->put('miaoshai_items', $miaoshai_items);


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
