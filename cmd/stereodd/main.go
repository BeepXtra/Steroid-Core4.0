package main

import (
	"os"

	svrcmd "github.com/cosmos/cosmos-sdk/server/cmd"

	"github.com/beepxtra/steroid-core4.0/app"
	"github.com/beepxtra/steroid-core4.0/cmd/stereodd/cmd"
)

func main() {
	rootCmd := cmd.NewRootCmd()
	if err := svrcmd.Execute(rootCmd, "STEREODD", app.DefaultNodeHome); err != nil {
		os.Exit(1)
	}
}
