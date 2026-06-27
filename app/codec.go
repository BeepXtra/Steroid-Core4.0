package app

import (
	"github.com/cosmos/cosmos-sdk/codec"
	codectypes "github.com/cosmos/cosmos-sdk/codec/types"
	cryptocodec "github.com/cosmos/cosmos-sdk/crypto/codec"
	"github.com/cosmos/cosmos-sdk/crypto/keys/secp256k1"
	cryptotypes "github.com/cosmos/cosmos-sdk/crypto/types"
	"github.com/cosmos/cosmos-sdk/x/auth/tx"

	appparams "github.com/beepxtra/steroid-core4.0/app/params"
)

// MakeEncodingConfig returns an EncodingConfig for the Steroid chain.
//
// TODO(D3): replace bech32 with a custom base58 address codec (secp256k1 keys
// unchanged) to preserve first-stage addresses through migration. See D3.
func MakeEncodingConfig() appparams.EncodingConfig {
	ir := codectypes.NewInterfaceRegistry()
	cdc := codec.NewProtoCodec(ir)
	amino := codec.NewLegacyAmino()

	// Register all v1 module interfaces via ModuleBasics (same package).
	ModuleBasics.RegisterInterfaces(ir)
	ModuleBasics.RegisterLegacyAminoCodec(amino)

	// Explicit crypto registrations — belt-and-suspenders for key types that
	// show up in genesis before any module is initialised.
	cryptocodec.RegisterInterfaces(ir)
	ir.RegisterImplementations((*cryptotypes.PubKey)(nil), &secp256k1.PubKey{})
	ir.RegisterImplementations((*cryptotypes.PrivKey)(nil), &secp256k1.PrivKey{})

	return appparams.EncodingConfig{
		InterfaceRegistry: ir,
		Codec:             cdc,
		TxConfig:          tx.NewTxConfig(cdc, tx.DefaultSignModes),
		Amino:             amino,
	}
}
