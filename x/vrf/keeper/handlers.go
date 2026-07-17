package keeper

import (
	"time"

	abci "github.com/cometbft/cometbft/abci/types"

	sdk "github.com/cosmos/cosmos-sdk/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/proposer"
	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// PrepareProposalHandler returns a sdk.PrepareProposalHandler that, when this
// node happens to be the round's proposer and has a registered VRF key,
// computes and prepends a VRF proof over the height's seed (D1a Decision 4).
// privKeyProvider is called fresh on every invocation (not cached at
// construction time) so a key loaded/rotated after startup is picked up.
func (k Keeper) PrepareProposalHandler(privKeyProvider func() []byte) sdk.PrepareProposalHandler {
	return func(ctx sdk.Context, req *abci.RequestPrepareProposal) (*abci.ResponsePrepareProposal, error) {
		txs := req.Txs

		privKey := privKeyProvider()
		if len(privKey) > 0 {
			operator, err := k.OperatorAddressByConsAddr(ctx, req.ProposerAddress)
			if err == nil {
				if has, herr := k.HasKey(ctx, operator); herr == nil && has {
					if s, serr := k.currentSeed(ctx, req.Height); serr == nil {
						if _, proof, perr := proposer.Prove(privKey, s); perr == nil {
							proofTx := k.EncodeProofTx(&types.VRFProposalProof{
								ValidatorAddress: operator,
								Proof:            proof,
							})
							combined := make([][]byte, 0, len(txs)+1)
							combined = append(combined, proofTx)
							combined = append(combined, txs...)
							txs = combined
						}
					}
				}
			}
		}

		return &abci.ResponsePrepareProposal{Txs: txs}, nil
	}
}

// ProcessProposalHandler returns a sdk.ProcessProposalHandler enforcing D1a
// Decision 4 via EvaluateProposal.
func (k Keeper) ProcessProposalHandler(fallbackWindow time.Duration) sdk.ProcessProposalHandler {
	return func(ctx sdk.Context, req *abci.RequestProcessProposal) (*abci.ResponseProcessProposal, error) {
		var injected *types.VRFProposalProof
		if len(req.Txs) > 0 {
			if proof, ok := k.DecodeProofTx(req.Txs[0]); ok {
				injected = proof
			}
		}

		accept, _, err := k.EvaluateProposal(ctx, req.Height, req.Time, req.ProposerAddress, injected, fallbackWindow)
		if err != nil {
			return nil, err
		}
		if !accept {
			return &abci.ResponseProcessProposal{Status: abci.ResponseProcessProposal_REJECT}, nil
		}
		return &abci.ResponseProcessProposal{Status: abci.ResponseProcessProposal_ACCEPT}, nil
	}
}

// PreBlockerHandler returns a sdk.PreBlocker that records the accepted
// proposal's outcome (RecordAcceptedProposal) so the next height's seed and
// fallback-window check have up-to-date state.
func (k Keeper) PreBlockerHandler(fallbackWindow time.Duration) sdk.PreBlocker {
	return func(ctx sdk.Context, req *abci.RequestFinalizeBlock) (*sdk.ResponsePreBlock, error) {
		var injected *types.VRFProposalProof
		if len(req.Txs) > 0 {
			if proof, ok := k.DecodeProofTx(req.Txs[0]); ok {
				injected = proof
			}
		}

		if err := k.RecordAcceptedProposal(ctx, req.Height, req.Time, req.ProposerAddress, injected, fallbackWindow); err != nil {
			return nil, err
		}
		return &sdk.ResponsePreBlock{}, nil
	}
}
