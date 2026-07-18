package gateway

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/gorilla/mux"
)

// nilClient is a zero NodeClient for handler tests that don't touch the chain.
var nilClient = &NodeClient{}

func newTestRouter() *mux.Router {
	r := mux.NewRouter()
	registerRoutes(r, nilClient)
	return r
}

func do(t *testing.T, r *mux.Router, method, path string) *httptest.ResponseRecorder { //nolint:unparam
	t.Helper()
	req := httptest.NewRequest(method, path, nil)
	rr := httptest.NewRecorder()
	r.ServeHTTP(rr, req)
	return rr
}

func TestInfoEndpoint(t *testing.T) {
	r := newTestRouter()
	rr := do(t, r, http.MethodGet, "/api")
	if rr.Code != http.StatusOK {
		t.Fatalf("GET /api: want 200, got %d", rr.Code)
	}
	if !strings.Contains(rr.Body.String(), `"status":"ok"`) {
		t.Fatalf("unexpected body: %s", rr.Body.String())
	}
}

func TestVersionEndpoint(t *testing.T) {
	r := newTestRouter()
	rr := do(t, r, http.MethodGet, "/api/version")
	if rr.Code != http.StatusOK {
		t.Fatalf("GET /api/version: want 200, got %d", rr.Code)
	}
	if !strings.Contains(rr.Body.String(), AppVersion) {
		t.Fatalf("version missing from body: %s", rr.Body.String())
	}
}

func TestCheckAddressEndpoint(t *testing.T) {
	r := newTestRouter()
	// "0OIl" contains base58-invalid characters (0, O, I, l are excluded from
	// the Bitcoin alphabet), so the codec must reject it.
	rr := do(t, r, http.MethodGet, "/api/checkaddress?address=0OIl")
	if rr.Code != http.StatusOK {
		t.Fatalf("want 200, got %d", rr.Code)
	}
	if !strings.Contains(rr.Body.String(), `"valid":false`) {
		t.Fatalf("expected valid:false, body=%s", rr.Body.String())
	}
}

func TestGenerateWalletEndpoint(t *testing.T) {
	r := newTestRouter()
	rr := do(t, r, http.MethodGet, "/api/generate_wallet")
	if rr.Code != http.StatusOK {
		t.Fatalf("want 200, got %d", rr.Code)
	}
	body := rr.Body.String()
	for _, field := range []string{`"address"`, `"public_key"`, `"private_key"`} {
		if !strings.Contains(body, field) {
			t.Fatalf("field %s missing from body: %s", field, body)
		}
	}
}

func TestD5StubReturns501(t *testing.T) {
	r := newTestRouter()
	rr := do(t, r, http.MethodGet, "/api/getaliasaddress")
	if rr.Code != http.StatusNotImplemented {
		t.Fatalf("D5 stub: want 501, got %d", rr.Code)
	}
}

func TestSendMissingParam(t *testing.T) {
	r := newTestRouter()
	rr := do(t, r, http.MethodGet, "/api/send")
	if rr.Code != http.StatusBadRequest {
		t.Fatalf("want 400, got %d", rr.Code)
	}
}

func TestUbpcToBPC(t *testing.T) {
	cases := []struct {
		in   int64
		want string
	}{
		{0, "0.00000000"},
		{100_000_000, "1.00000000"},
		{2_100_000_000_000_000, "21000000.00000000"},
		{1, "0.00000001"},
	}
	for _, c := range cases {
		if got := ubpcToBPC(c.in); got != c.want {
			t.Errorf("ubpcToBPC(%d) = %q, want %q", c.in, got, c.want)
		}
	}
}
