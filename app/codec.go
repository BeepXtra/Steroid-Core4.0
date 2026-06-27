package app

import (
	"github.com/cosmos/cosmos-sdk/codec"
	codectypes "github.com/cosmos/cosmos-sdk/codec/types"
	"github.com/cosmos/cosmos-sdk/codec/address"
	cryptocodec "github.com/cosmos/cosmos-sdk/crypto/codec"
	"github.com/cosmos/cosmos-sdk/crypto/keys/secp256k1"
	cryptotypes "github.com/cosmos/cosmos-sdk/crypto/types"
	"github.com/cosmos/cosmos-sdk/x/auth/tx"
	authtypes "github.com/cosmos/cosmos-sdk/x/auth/types"
	banktypes "github.com/cosmos/cosmos-sdk/x/bank/types"
	govv1 "github.com/cosmos/cosmos-sdk/x/gov/types/v1"
	stakingtypes "github.com/cosmos/cosmos-sdk/x/staking/types"

	appparams "github.com/beepxtra/steroid-core4.0/app/params"
)

// MakeEncodingConfig returns an EncodingConfig for the Steroid chain.
//
// TODO(D3): replace address.NewBech32Codec with a custom base58 address codec
// (secp256k1 keys unchanged) to preserve first-stage addresses through migration.
// See docs/FUTURE-ARCHITECTURE.md §D3.
func MakeEncodingConfig() appparams.EncodingConfig {
	ir := codectypes.NewInterfaceRegistry()
	cdc := codec.NewProtoCodec(ir)
	amino := codec.NewLegacyAmino()

	authtypes.RegisterInterfaces(ir)
	banktypes.RegisterInterfaces(ir)
	stakingtypes.RegisterInterfaces(ir)
	govv1.RegisterInterfaces(ir)
	cryptocodec.RegisterInterfaces(ir)

	ir.RegisterImplementations((*cryptotypes.PubKey)(nil), &secp256k1.PubKey{})
	ir.RegisterImplementations((*cryptotypes.PrivKey)(nil), &secp256k1.PrivKey{})

	authtypes.RegisterLegacyAminoCodec(amino)

	// Standard bech32 codec — will be swapped for base58 at D3 implementation.
	_ = address.NewBech32Codec("steroid")

	return appparams.EncodingConfig{
		InterfaceRegistry: ir,
		Codec:             cdc,
		TxConfig:          tx.NewTxConfig(cdc, tx.DefaultSignModes),
		Amino:             amino,
	}
}
