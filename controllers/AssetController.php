<?php

class AssetController extends AbstractController {

    /**
     * GET /asset/...
     *
     * Routing table (url_elements includes the controller segment):
     *   [asset]                        → 1 element  → list assets
     *   [asset, list]                  → 2 elements → list assets (explicit)
     *   [asset, get,       {id}]       → 3 elements → get asset details
     *   [asset, stats,     {id}]       → 3 elements → get asset statistics
     *   [asset, holders,   {id}]       → 3 elements → get holder list
     *   [asset, market,    {id}]       → 3 elements → get all market orders
     *   [asset, dividends, {id}]       → 3 elements → get dividend history
     *   [asset, myassets,  {account}]  → 3 elements → get assets held by account
     *   [asset, balance, {asset}:{acc}]→ 3 elements → get balance (colon-separated)
     *   [asset, market, {id}, bid|ask] → 4 elements → filtered market orders
     *   [asset, balance,   {asset}, {account}] → 4 elements → get balance
     */
    public function get($request) {
        return $this->beginRequest($request);
    }

    public function post($request) {
        return $this->beginRequest($request, 'post');
    }

    public function put($request) {
        return $this->beginRequest($request, 'put');
    }

    public function delete($request) {
        return $this->beginRequest($request, 'delete');
    }

    protected function beginRequest($request, $method = null) {
        if (!$method || $method == 'get') {
            return $this->getRequest($request);
        } elseif ($method == 'post') {
            return $this->postRequest($request);
        } elseif ($method == 'put') {
            return $this->putRequest($request);
        } elseif ($method == 'delete') {
            return $this->deleteRequest($request);
        }
    }

    protected function getRequest($request) {
        $els = $request->url_elements;
        $n   = count($els);

        switch ($n) {
            case 1:
                // GET /asset  → list first 50 assets
                $data = $this->listAssets(50, 0);
                break;

            case 2:
                switch ($els[1]) {
                    case 'list':
                        $data = $this->listAssets(50, 0);
                        break;
                    default:
                        $data = $this->error('Unknown action. Try /asset/list', 2);
                        break;
                }
                break;

            case 3:
                $action = $els[1];
                $param  = urldecode($els[2]);

                switch ($action) {
                    case 'get':
                        $data = $this->getAsset($param);
                        break;

                    case 'stats':
                        $data = $this->getStats($param);
                        break;

                    case 'holders':
                        $data = $this->getHolders($param);
                        break;

                    case 'market':
                        $data = $this->getMarket($param);
                        break;

                    case 'dividends':
                        $data = $this->getDividends($param);
                        break;

                    case 'myassets':
                        $data = $this->getAccountAssets($param);
                        break;

                    case 'balance':
                        // Support colon-separated: /asset/balance/ASSET_ID:ACCOUNT_ID
                        if (strpos($param, ':') !== false) {
                            list($asset, $account) = explode(':', $param, 2);
                            $data = $this->getBalance($asset, $account);
                        } else {
                            $data = $this->error('Provide asset and account separated by colon, e.g. /asset/balance/{assetId}:{accountId}', 2);
                        }
                        break;

                    case 'list':
                        // /asset/list/{limit}
                        $data = $this->listAssets(intval($param), 0);
                        break;

                    default:
                        $data = $this->error('Unknown action', 2);
                        break;
                }
                break;

            case 4:
                $action  = $els[1];
                $param   = urldecode($els[2]);
                $param2  = urldecode($els[3]);

                switch ($action) {
                    case 'balance':
                        // GET /asset/balance/{assetId}/{accountId}
                        $data = $this->getBalance($param, $param2);
                        break;

                    case 'market':
                        // GET /asset/market/{assetId}/bid|ask
                        $type = ($param2 === 'bid' || $param2 === 'ask') ? $param2 : '';
                        $data = $this->getMarket($param, $type);
                        break;

                    case 'list':
                        // GET /asset/list/{limit}/{offset}
                        $data = $this->listAssets(intval($param), intval($param2));
                        break;

                    default:
                        $data = $this->error('Unknown action', 2);
                        break;
                }
                break;

            default:
                $data = $this->error('Invalid request. Please check documentation', 3);
                break;
        }

        return $data ?: $this->error('Something went wrong with your request', 5);
    }

    protected function postRequest($request) {
        // Asset creation is submitted via the standard /api/send endpoint (version 50 transaction).
        return $this->error('Asset creation is submitted via POST /api/send with version=50', 1);
    }

    protected function putRequest($request) {
        return $this->error('Method not supported', 1);
    }

    protected function deleteRequest($request) {
        return $this->error('Method not supported', 1);
    }

    // -------------------------------------------------------------------------
    // Internal helpers — delegate to SAssets via $this->sapi->sassets
    // -------------------------------------------------------------------------

    private function listAssets($limit = 50, $offset = 0) {
        $assets = $this->sapi->sassets->list_assets($limit, $offset);
        if ($assets === false || $assets === []) {
            return $this->success([]);
        }
        return $this->response($assets);
    }

    private function getAsset($id) {
        $asset = $this->sapi->sassets->get($id);
        if (!$asset) {
            return $this->error('Asset not found', 4);
        }
        return $this->response($asset);
    }

    private function getStats($id) {
        $stats = $this->sapi->sassets->get_stats($id);
        if (!$stats) {
            return $this->error('Asset not found', 4);
        }
        return $this->response($stats);
    }

    private function getHolders($id) {
        $holders = $this->sapi->sassets->get_holders($id);
        if ($holders === false) {
            return $this->error('Asset not found', 4);
        }
        return $this->response($holders);
    }

    private function getMarket($id, $type = '') {
        $orders = $this->sapi->sassets->get_market_orders($id, $type);
        if ($orders === false) {
            return $this->error('Asset not found', 4);
        }
        return $this->response($orders);
    }

    private function getDividends($id) {
        $history = $this->sapi->sassets->get_dividend_history($id);
        if ($history === false) {
            return $this->error('Asset not found', 4);
        }
        return $this->response($history);
    }

    private function getBalance($asset, $account) {
        $balance = $this->sapi->sassets->get_balance($asset, $account);
        if ($balance === false) {
            return $this->error('Asset or account not found', 4);
        }
        return $this->response($balance);
    }

    private function getAccountAssets($account) {
        $assets = $this->sapi->sassets->get_account_assets($account);
        if ($assets === false) {
            return $this->error('Account not found', 4);
        }
        return $this->response($assets);
    }
}
