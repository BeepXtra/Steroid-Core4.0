package gateway

import "encoding/json"

// ubpcPerBPC is the number of ubpc units in one BPC (8 decimal places, like satoshis).
// This is a build-time parameter (D4); change here when the emission curve is finalised.
const ubpcPerBPC = 100_000_000

// AppVersion is returned by /api/version and /api/node-info.
const AppVersion = "4.0.0"

// response is the standard JSON envelope for every /api/* response.
type response struct {
	Status string      `json:"status"`
	Data   interface{} `json:"data"`
}

func ok(data interface{}) response    { return response{"ok", data} }
func apiErr(msg string) response      { return response{"error", msg} }
func notImpl(feature string) response { return apiErr(feature + " not yet implemented (pending D5)") }

// blockData is the shape returned by /api/currentblock and /api/getblock/:height.
type blockData struct {
	ID         string `json:"id"`
	Generator  string `json:"generator"`
	Height     int64  `json:"height"`
	Date       int64  `json:"date"`
	Nonce      string `json:"nonce"`
	Signature  string `json:"signature"`
	Difficulty int    `json:"difficulty"`
	Argon      string `json:"argon"`
	TxCount    int    `json:"tx_count"`
}

// txData is a normalised transaction shape used in /api/gettransaction and /api/gettransactions.
type txData struct {
	ID            string  `json:"id"`
	Block         string  `json:"block"`
	Height        int64   `json:"height"`
	Confirmations int64   `json:"confirmations"`
	Date          int64   `json:"date"`
	Src           string  `json:"src"`
	Dst           string  `json:"dst"`
	Val           string  `json:"val"`
	Fee           string  `json:"fee"`
	Message       string  `json:"message"`
	PublicKey     string  `json:"public_key"`
	Signature     string  `json:"signature"`
	Type          string  `json:"type"`
	Version       int     `json:"version"`
}

// validatorData is one entry in the /api/masternodes response.
type validatorData struct {
	Address    string `json:"address"`
	PubKey     string `json:"pub_key"`
	VotingPower int64 `json:"voting_power"`
	Moniker    string `json:"moniker"`
	Status     string `json:"status"`
	Jailed     bool   `json:"jailed"`
	Tokens     string `json:"tokens"`
}

// nodeInfoData is returned by /api/node-info.
type nodeInfoData struct {
	Hostname    string  `json:"hostname"`
	Version     string  `json:"version"`
	DBVersion   string  `json:"dbversion"`
	Accounts    int     `json:"accounts"`
	Transactions int    `json:"transactions"`
	Mempool     int     `json:"mempool"`
	Masternodes int     `json:"masternodes"`
	Peers       int     `json:"peers"`
	Height      int64   `json:"height"`
	PassivePeering bool `json:"passive_peering"`
	PublicKey   string  `json:"public_key"`
	LoadAvg     float64 `json:"loadavg"`
	Coin        string  `json:"coin"`
	System      string  `json:"system"`
	WebServer   string  `json:"webserver"`
	DBEngine    string  `json:"dbengine"`
}

// walletData is returned by /api/generate_wallet.
type walletData struct {
	Address    string `json:"address"`
	PublicKey  string `json:"public_key"`
	PrivateKey string `json:"private_key"`
}

// marshalJSON writes r as JSON to w; errors are silently dropped
// (the caller already holds the http.ResponseWriter).
func writeJSON(w interface{ Write([]byte) (int, error) }, r response) {
	b, _ := json.Marshal(r)
	b = append(b, '\n')
	_, _ = w.Write(b)
}
