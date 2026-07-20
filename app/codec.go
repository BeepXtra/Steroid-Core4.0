package app

import (
	"github.com/cosmos/cosmos-sdk/codec"
	codectypes "github.com/cosmos/cosmos-sdk/codec/types"
	cryptocodec "github.com/cosmos/cosmos-sdk/crypto/codec"
	"github.com/cosmos/cosmos-sdk/crypto/keys/secp256k1"
	cryptotypes "github.com/cosmos/cosmos-sdk/crypto/types"
	"github.com/cosmos/cosmos-sdk/x/auth/tx"
	txsigning "github.com/cosmos/cosmos-sdk/x/tx/signing"
	"github.com/cosmos/gogoproto/proto"

	steroidaddress "github.com/beepxtra/steroid-core4.0/app/address"
	appparams "github.com/beepxtra/steroid-core4.0/app/params"
)

// MakeEncodingConfig returns an EncodingConfig for the Steroid chain.
func MakeEncodingConfig() appparams.EncodingConfig {
	// D3: wire base58 codec into the signing context so the InterfaceRegistry
	// can convert addresses during tx signing (gentx, send, etc.).
	ir, err := codectypes.NewInterfaceRegistryWithOptions(codectypes.InterfaceRegistryOptions{
		ProtoFiles: proto.HybridResolver,
		SigningOptions: txsigning.Options{
			AddressCodec:          steroidaddress.Codec{},
			ValidatorAddressCodec: steroidaddress.Codec{},
		},
	})
	if err != nil {
		panic(err)
	}
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
