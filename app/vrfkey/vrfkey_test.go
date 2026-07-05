package vrfkey_test

import (
	"testing"

	"github.com/stretchr/testify/require"

	"github.com/beepxtra/steroid-core4.0/app/vrfkey"
)

func TestLoadOrGenerate_GeneratesThenPersists(t *testing.T) {
	dir := t.TempDir()

	priv1, pub1, err := vrfkey.LoadOrGenerate(dir)
	require.NoError(t, err)
	require.NotEmpty(t, priv1)
	require.NotEmpty(t, pub1)

	priv2, pub2, err := vrfkey.LoadOrGenerate(dir)
	require.NoError(t, err)
	require.Equal(t, priv1, priv2, "second call must load the same persisted key, not generate a new one")
	require.Equal(t, pub1, pub2)
}

func TestLoadOrGenerate_DifferentDirsGetDifferentKeys(t *testing.T) {
	priv1, _, err := vrfkey.LoadOrGenerate(t.TempDir())
	require.NoError(t, err)
	priv2, _, err := vrfkey.LoadOrGenerate(t.TempDir())
	require.NoError(t, err)
	require.NotEqual(t, priv1, priv2)
}
