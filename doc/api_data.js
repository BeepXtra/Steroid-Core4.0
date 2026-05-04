//var x = window.location.protocol+"//"+window.location.hostname;
define({ "api": [
  {
    "type": "get",
    "url": window.location.hostname+"/api",
    "title": "01. Basic Information",
    "name": "Info",
    "group": "API",
    "description": "<p>Each API call will return the result in JSON format. There are 2 objects, &quot;status&quot; and &quot;data&quot;.</p> <p>The &quot;status&quot; object returns &quot;ok&quot; when the transaction is successful and &quot;error&quot; on failure.</p> <p>The &quot;data&quot; object returns the requested data, as sub-objects.</p> <p>The parameters must be sent either as POST['data'], json encoded array or independently as GET.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "String",
            "optional": false,
            "field": "status",
            "description": "<p>&quot;ok&quot;</p>"
          },
          {
            "group": "Success 200",
            "type": "String",
            "optional": false,
            "field": "data",
            "description": "<p>The data provided by the api will be under this object.</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": '{"success":true,"request":{"method":"GET","controller":"api","resource":null,"parameters":"\/api","url_elements":["api"],"mileage":"0.0086100101470947"},"error":{"errorid":0,"message":0},"data":{"info":"Basic API Information","version":"1.0.1b"}}',
          "type": "json"
        }
      ]
    },
    "error": {
      "fields": {
        "Error 4xx": [
          {
            "group": "Error 4xx",
            "type": "String",
            "optional": false,
            "field": "status",
            "description": "<p>&quot;error&quot;</p>"
          },
          {
            "group": "Error 4xx",
            "type": "String",
            "optional": false,
            "field": "result",
            "description": "<p>Information regarding the error</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response:",
          "content": "{\n  \"status\": \"error\",\n  \"data\": \"The requested action could not be completed.\"\n}",
          "type": "json"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/base58/$string",
    "title": "03. base58",
    "name": "base58",
    "group": "API",
    "description": "<p>Converts a string to base58.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Input string</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Output string</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetbalance",
    "title": "23. assetbalance",
    "name": "assetbalance",
    "group": "API",
    "description": "<p>Get Asset Balance.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "$asset_address:$wallet_address",
            "description": "<p>$asset_address The wallet of the smart contract   :$wallet_address The wallet that you want to check the balance</p>"
          } 
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>Check Asset Balance.</p>"
          }
        ]
      },
       "examples": [
        {
          "title": "Success-Response:",
          "content": '{\r\n  \"success\": true,\r\n  \"request\": {\r\n    \"method\": \"GET\",\r\n    \"controller\": \"api\",\r\n    \"resource\": \"assetbalance\",\r\n    \"parameters\": \"\/api\/assetbalance\/$asset_address:$wallet_address\",\r\n    \"url_elements\": [\r\n      \"api\",\r\n      \"assetbalance\",\r\n      \"$asset_address:$wallet_address\"\r\n    ],\r\n    \"mileage\": \"0.011698007583618\"\r\n  },\r\n  \"error\": {\r\n    \"errorid\": 0,\r\n    \"message\": 0\r\n  },\r\n  \"data\": {\r\n    \"status\": \"ok\",\r\n    \"data\": [\r\n      {\r\n        \"asset\": \"$asset_address\",\r\n        \"alias\": \"Token Name\",\r\n        \"account\": \" $wallet_address\",\r\n        \"balance\": 10\r\n      }\r\n    ],\r\n    \"coin\": \"bpc\"\r\n  }\r\n}',
          "type": "json"
        }
      ]
    },
    
    "version": "1.3.0",
    "filename": "./api",
    "groupTitle": "API"
  },{
    "type": "get",
    "url": window.location.hostname+"/api/checkaddress",
    "title": "22. checkAddress",
    "name": "checkAddress",
    "group": "API",
    "description": "<p>Checks the validity of an address.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "account",
            "description": "<p>Account id / address</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>True if the address is valid, false otherwise.</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/checksignature/$public_key/$signature/$data",
    "title": "17. checkSignature",
    "name": "checkSignature",
    "group": "API",
    "description": "<p>Checks a signature against a public key</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>signature</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>signed data</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>true or false</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/currentblock",
    "title": "10. currentBlock",
    "name": "currentBlock",
    "group": "API",
    "description": "<p>Returns the current block.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Blocks id</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "generator",
            "description": "<p>Block Generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Height</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Block's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "nonce",
            "description": "<p>Mining nonce</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Signature signed by the generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "difficulty",
            "description": "<p>The base target / difficulty</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "argon",
            "description": "<p>Mining argon hash</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/generate_wallet",
    "title": "09. generateWallet",
    "name": "generateWallet",
    "group": "API",
    "description": "<p>Generates a new account. This function should only be used when the node is on the same host or over a really secure network.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "address",
            "description": "<p>Account address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "private_key",
            "description": "<p>Private key</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getaddress/$public_key",
    "title": "02. getAddress",
    "name": "getAddress",
    "group": "API",
    "description": "<p>Converts the public key to an BPC address.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>The public key</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Contains the address</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getalias",
    "title": "19. getAlias",
    "name": "getAlias",
    "group": "API",
    "description": "<p>Returns the alias of an account</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "account",
            "description": "<p>Account id / address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>alias</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getbalance/$address",
    "title": "04. getBalance",
    "name": "getBalance",
    "group": "API",
    "description": "<p>Returns the balance of a specific account or public key.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "account",
            "description": "<p>Account id / address</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "alias",
            "description": "<p>alias</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The BPC balance</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getblock/$height",
    "title": "11. getBlock",
    "name": "getBlock",
    "group": "API",
    "description": "<p>Returns the block.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block Height</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Block id</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "generator",
            "description": "<p>Block Generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Height</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Block's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "nonce",
            "description": "<p>Mining nonce</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Signature signed by the generator</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "difficulty",
            "description": "<p>The base target / difficulty</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "argon",
            "description": "<p>Mining argon hash</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getblocktransactions/$height or $blockid:$includeMiningRewards",
    "title": "12. getBlockTransactions",
    "name": "getBlockTransactions",
    "group": "API",
    "description": "<p>Returns the transactions of a specific block.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "height",
            "description": "<p>Block Height</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "block",
            "description": "<p>Block id</p>"
          },
          {
            "group": "Parameter",
            "type": "boolean",
            "optional": true,
            "field": "includeMiningRewards",
            "description": "<p>Include mining rewards (Default false)</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "block",
            "description": "<p>Block ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "confirmations",
            "description": "<p>Number of confirmations</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Transaction destination</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fee",
            "description": "<p>The transaction's fee</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block height</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID/HASH</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Transaction's message</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Transaction's signature</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Account's public_key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "src",
            "description": "<p>Sender's address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>&quot;debit&quot;, &quot;credit&quot; or &quot;mempool&quot;</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "version",
            "description": "<p>Transaction version</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getpendingbalance",
    "title": "05. getPendingBalance",
    "name": "getPendingBalance",
    "group": "API",
    "description": "<p>Returns the pending balance, which includes pending transactions, of a specific account or public key.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "public_key",
            "description": "<p>Public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "account",
            "description": "<p>Account id / address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The BPC balance</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/getpublickey",
    "title": "08. getPublicKey",
    "name": "getPublicKey",
    "group": "API",
    "description": "<p>Returns the public key of a specific account.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "account",
            "description": "<p>Account id / address</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The public key</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/gettransaction/$id",
    "title": "07. getTransaction",
    "name": "getTransaction",
    "group": "API",
    "description": "<p>Returns one transaction.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "block",
            "description": "<p>Block ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "confirmation",
            "description": "<p>Number of confirmations</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Transaction destination</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fee",
            "description": "<p>The transaction's fee</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block height</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID/HASH</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Transaction's message</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Transaction's signature</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Account's public_key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "src",
            "description": "<p>Sender's address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>&quot;debit&quot;, &quot;credit&quot; or &quot;mempool&quot;</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "version",
            "description": "<p>Transaction version</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/gettransactions/$address",
    "title": "06. getTransactions",
    "name": "getTransactions",
    "group": "API",
    "description": "<p>Returns the latest transactions of an account.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "account",
            "description": "<p>Account id / address</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "limit",
            "description": "<p>Number of confirmed transactions, max 100, min 1</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "block",
            "description": "<p>Block ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "confirmation",
            "description": "<p>Number of confirmations</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Transaction destination</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "fee",
            "description": "<p>The transaction's fee</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>Block height</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Transaction ID/HASH</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Transaction's message</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "signature",
            "description": "<p>Transaction's signature</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Account's public_key</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "src",
            "description": "<p>Sender's address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>&quot;debit&quot;, &quot;credit&quot; or &quot;mempool&quot;</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "version",
            "description": "<p>Transaction version</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/masternodes",
    "title": "18. masternodes",
    "name": "masternodes",
    "group": "API",
    "description": "<p>Returns all the masternode data</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>masternode date</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/mempoolsize",
    "title": "15. mempoolSize",
    "name": "mempoolSize",
    "group": "API",
    "description": "<p>Returns the number of transactions in mempool.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data",
            "description": "<p>Number of mempool transactions</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/node-info",
    "title": "21. node-info",
    "name": "node_info",
    "group": "API",
    "description": "<p>Returns details about the node.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>A collection of data about the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.hostname",
            "description": "<p>The hostname of the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.version",
            "description": "<p>The current version of the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.dbversion",
            "description": "<p>The database schema version for the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.accounts",
            "description": "<p>The number of accounts known by the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.transactions",
            "description": "<p>The number of transactions known by the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.mempool",
            "description": "<p>The number of transactions in the mempool.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.masternodes",
            "description": "<p>The number of masternodes known by the node.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.peers",
            "description": "<p>The number of valid peers.</p>"
          },
            {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.height",
            "description": "<p>The current height of the node.</p>"
          },
            {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.passive_peering",
            "description": "<p>Passive peering.</p>"
          },
            {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.public_key",
            "description": "<p>The $public_key that is connected to the current node.</p>"
          },
            {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.loadavg",
            "description": "<p>Load Average Of the node.</p>"
          },
            {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.disk",
            "description": "<p>Node total disk and Node available space .</p>"
          },
            {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.memory",
            "description": "<p>Node total memory and Node available memory .</p>"
          },
            {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.php",
            "description": "<p>PHP Version.</p>"
          },
            {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.system",
            "description": "<p>Node Operating System.</p>"
          },
            {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.webserver",
            "description": "<p>Node Web Server.</p>"
          },
            {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.dbengine",
            "description": "<p>Node db Engine.</p>"
          },
            {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.coin",
            "description": "<p>Bpc</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/randomnumber",
    "title": "16. randomNumber",
    "name": "randomNumber",
    "group": "API",
    "description": "<p>Returns a random number based on an BPC block id.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "height",
            "description": "<p>The height of the block on which the random number will be based on (should be a future block when starting)</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "min",
            "description": "<p>Minimum number (default 1)</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "max",
            "description": "<p>Maximum number</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "seed",
            "description": "<p>A seed to generate different numbers for each use cases.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data",
            "description": "<p>The random number</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/sanity",
    "title": "20. sanity",
    "name": "sanity",
    "group": "API",
    "description": "<p>Returns details about the node's sanity process.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>A collection of data about the sanity process.</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.sanity_running",
            "description": "<p>Whether the sanity process is currently running.</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.last_sanity",
            "description": "<p>The timestamp for the last time the sanity process was run.</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.sanity_sync",
            "description": "<p>Whether the sanity process is currently synchronising.</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/send",
    "title": "14. send",
    "name": "send",
    "group": "API",
    "description": "<p>Sends a transaction.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": false,
            "field": "val",
            "description": "<p>Transaction value (without fees)</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "dst",
            "description": "<p>Destination address</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>Sender's public key</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "signature",
            "description": "<p>Transaction signature. It's recommended that the transaction is signed before being sent to the node to avoid sending your private key to the node.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "private_key",
            "description": "<p>Sender's private key. Only to be used when the transaction is not signed locally.</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "date",
            "description": "<p>Transaction's date in UNIX TIMESTAMP format. Requried when the transaction is pre-signed.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "message",
            "description": "<p>A message to be included with the transaction. Maximum 128 chars.</p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "version",
            "description": "<p>The version of the transaction. 1 to send coins.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Transaction id</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/version",
    "title": "13. version",
    "name": "version",
    "group": "API",
    "description": "<p>Returns the node's version.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Version</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/totalsupply",
    "title": "24. totalSupply",
    "name": "totalSupply",
    "group": "API",
    "description": "<p>Returns the total issued supply of BPC coins.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "total_supply",
            "description": "<p>Total issued BPC supply (8 decimal places)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "{\"status\":\"ok\",\"data\":{\"total_supply\":\"21000000.00000000\"}}",
          "type": "json"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/circsupply",
    "title": "25. circSupply",
    "name": "circSupply",
    "group": "API",
    "description": "<p>Returns the circulating supply of BPC — total supply minus known treasury/reserve addresses.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "circulating_supply",
            "description": "<p>Circulating BPC supply (8 decimal places)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "{\"status\":\"ok\",\"data\":{\"circulating_supply\":\"18500000.00000000\"}}",
          "type": "json"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetlist",
    "title": "26. assetList",
    "name": "assetList",
    "group": "API",
    "description": "<p>Returns a paginated list of all assets created on the chain. Returns the first 50 assets ordered by creation height (newest first). Use <code>/api/assetlist/{limit}</code> or <code>/api/assetlist/{limit}/{offset}</code> for custom pagination.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "limit",
            "description": "<p>Number of assets to return (1–200, default 50). Pass in URL: <code>/asset/list/{limit}</code></p>"
          },
          {
            "group": "Parameter",
            "type": "numeric",
            "optional": true,
            "field": "offset",
            "description": "<p>Pagination offset (default 0). Pass in URL: <code>/asset/list/{limit}/{offset}</code></p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Request-Examples:",
          "content": "GET /api/assetlist\nGET /api/assetlist/10\nGET /api/assetlist/10/20",
          "type": "text"
        }
      ]
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "data",
            "description": "<p>Array of asset objects</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Asset address (blockchain address of the issuing account)</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": true,
            "field": "data.alias",
            "description": "<p>Alias of the issuing account (if set)</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.max_supply",
            "description": "<p>Maximum number of units that can be issued</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.price",
            "description": "<p>Unit price in BPC (set by the issuer)</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.tradable",
            "description": "<p>Whether the asset can be traded on the built-in market</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.dividend_only",
            "description": "<p>If true, asset units are not transferable — dividends only</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.auto_dividend",
            "description": "<p>Whether automated dividend payouts are enabled</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.allow_bid",
            "description": "<p>Whether buy-side market orders (bids) are permitted</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.height",
            "description": "<p>Block height at which the asset was created</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/asset/$id",
    "title": "27. getAsset",
    "name": "getAsset",
    "group": "API",
    "description": "<p>Returns full details for a single asset, including its current circulating supply and holder count. The <code>$id</code> may be the asset address, the issuer's public key, or the account alias.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Asset address, issuer public key, or account alias</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Request-Example:",
          "content": "GET /api/asset/3G6Xa...",
          "type": "text"
        }
      ]
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Asset address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": true,
            "field": "data.alias",
            "description": "<p>Alias of the issuing account</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.max_supply",
            "description": "<p>Maximum issuable units</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.circulating_supply",
            "description": "<p>Units currently in circulation (sum of all holder balances)</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.holders",
            "description": "<p>Number of accounts holding at least 1 unit</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.price",
            "description": "<p>Unit price in BPC</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.tradable",
            "description": "<p>Whether the asset can be traded on the market</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.height",
            "description": "<p>Block height at creation</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetstats/$id",
    "title": "28. assetStats",
    "name": "assetStats",
    "group": "API",
    "description": "<p>Returns aggregate statistics for an asset: supply, holder count, open order book depth, last trade price, and total dividend distributions.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Asset address, issuer public key, or account alias</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Asset address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": true,
            "field": "data.alias",
            "description": "<p>Account alias</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.max_supply",
            "description": "<p>Maximum issuable units</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.circulating_supply",
            "description": "<p>Units currently in circulation</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.holders",
            "description": "<p>Number of accounts with a positive balance</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.open_bids",
            "description": "<p>Number of open buy orders</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.open_asks",
            "description": "<p>Number of open sell orders</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": true,
            "field": "data.last_trade_price",
            "description": "<p>Price (in BPC) of the most recent completed trade, or null if no trades yet</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.total_distributions",
            "description": "<p>Total number of dividend distribution events</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.created_at_height",
            "description": "<p>Block height at which the asset was created</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "{\"status\":\"ok\",\"data\":{\"id\":\"3G6Xa...\",\"alias\":\"MYTOKEN\",\"max_supply\":1000000,\"circulating_supply\":750000,\"holders\":42,\"open_bids\":5,\"open_asks\":3,\"last_trade_price\":\"0.50000000\",\"total_distributions\":12,\"created_at_height\":1500000}}",
          "type": "json"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetholders/$id",
    "title": "29. assetHolders",
    "name": "assetHolders",
    "group": "API",
    "description": "<p>Returns the list of accounts holding a given asset, ranked by balance (highest first).</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Asset address, issuer public key, or account alias</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "data",
            "description": "<p>Array of holder objects, ordered by balance descending</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.account",
            "description": "<p>Holder's blockchain address</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.balance",
            "description": "<p>Number of asset units held</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": true,
            "field": "data.alias",
            "description": "<p>Holder's account alias (if set)</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetmarket/$id",
    "title": "30. assetMarket",
    "name": "assetMarket",
    "group": "API",
    "description": "<p>Returns the open order book for an asset. Append <code>/bid</code> or <code>/ask</code> to filter to buy or sell orders: <code>/api/assetmarket/{id}/bid</code> or <code>/api/assetmarket/{id}/ask</code>. Without a filter, all open orders are returned.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Asset address, issuer public key, or account alias</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": true,
            "field": "type",
            "description": "<p><code>bid</code> (buy orders) or <code>ask</code> (sell orders). Omit for all open orders.</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Request-Examples:",
          "content": "GET /api/assetmarket/3G6Xa...\nGET /api/assetmarket/3G6Xa.../bid\nGET /api/assetmarket/3G6Xa.../ask",
          "type": "text"
        }
      ]
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "data",
            "description": "<p>Array of market order objects</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Order ID</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.asset",
            "description": "<p>Asset address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.public_key",
            "description": "<p>Public key of the order placer</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.type",
            "description": "<p><code>bid</code> or <code>ask</code></p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.price",
            "description": "<p>Order price per unit in BPC</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.quantity",
            "description": "<p>Number of asset units in the order</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.status",
            "description": "<p>0 = open, 1 = filled, 2 = cancelled</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.date",
            "description": "<p>Order placement date (UNIX timestamp)</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetdividends/$id",
    "title": "31. assetDividends",
    "name": "assetDividends",
    "group": "API",
    "description": "<p>Returns the dividend distribution history for an asset. Includes both manual distributions (transaction version 54) and automated per-block distributions (version 57).</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Asset address, issuer public key, or account alias</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "data",
            "description": "<p>Array of dividend distribution records, newest first</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Transaction ID</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.height",
            "description": "<p>Block height of the distribution</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.val",
            "description": "<p>Total BPC distributed</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.fee",
            "description": "<p>Transaction fee paid</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.date",
            "description": "<p>Distribution date (UNIX timestamp)</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.distribution_type",
            "description": "<p><code>manual</code> (v54) or <code>automated</code> (v57)</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/assetbalance/$assetId/$accountId",
    "title": "32. assetBalance",
    "name": "assetBalance",
    "group": "API",
    "description": "<p>Returns the balance of a specific asset for a given account. The asset and account can each be specified as an address, public key, or alias. Two URL forms are supported: slash-separated (<code>/api/assetbalance/{assetId}/{accountId}</code>) or colon-separated (<code>/api/assetbalance/{assetId}:{accountId}</code>).</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "assetId",
            "description": "<p>Asset address, issuer public key, or alias</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "accountId",
            "description": "<p>Account address, public key, or alias</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Request-Examples:",
          "content": "GET /api/assetbalance/3G6Xa.../5XXSb...\nGET /api/assetbalance/3G6Xa...:5XXSb...",
          "type": "text"
        }
      ]
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.asset",
            "description": "<p>Resolved asset address</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.account",
            "description": "<p>Resolved account address</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.balance",
            "description": "<p>Number of asset units held by the account (0 if none)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "{\"status\":\"ok\",\"data\":{\"asset\":\"3G6Xa...\",\"account\":\"5XXSb...\",\"balance\":500}}",
          "type": "json"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/myassets/$account",
    "title": "33. myAssets",
    "name": "myAssets",
    "group": "API",
    "description": "<p>Returns all assets held by a specific account where the balance is greater than zero. The account can be specified as an address, public key, or alias.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "account",
            "description": "<p>Account address, public key, or alias</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "data",
            "description": "<p>Array of asset holdings, ordered by balance descending</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.asset",
            "description": "<p>Asset address</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.balance",
            "description": "<p>Units held</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": true,
            "field": "data.asset_alias",
            "description": "<p>Alias of the asset's issuing account (if set)</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.max_supply",
            "description": "<p>Maximum issuable units for this asset</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data.tradable",
            "description": "<p>Whether this asset can be traded on the market</p>"
          },
          {
            "group": "Success 200",
            "type": "numeric",
            "optional": false,
            "field": "data.price",
            "description": "<p>Unit price in BPC set by the issuer</p>"
          }
        ]
      }
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "get",
    "url": window.location.hostname+"/api/propwallet/$public_key",
    "title": "34. propWallet",
    "name": "propWallet",
    "group": "API",
    "description": "<p>Registers a public key with this node, ensuring the associated account address is stored before any transactions arrive. Useful when setting up a new wallet on the network so peers have the account record in advance. Returns a success acknowledgement; the caller should verify the account is present afterwards.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "public_key",
            "description": "<p>The account's public key (base58-encoded secp256k1 public key)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Request-Example:",
          "content": "GET /api/propwallet/PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyjGMdVDanywM3CbqvswVqysqU8XS87FcjpqNijtpRSSQ36WexRDv3rJL5X8qpGvzvznuErSRMfb2G6aNoiaT3aEJ",
          "type": "text"
        }
      ]
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Confirmation message: &quot;Storing wallet attempted - check to verify&quot;</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "{\"status\":\"ok\",\"data\":\"Storing wallet attempted - check to verify\"}",
          "type": "json"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./api",
    "groupTitle": "API"
  },
  {
    "type": "php util.php",
    "url": "balance",
    "title": "Balance",
    "name": "balance",
    "group": "UTIL",
    "description": "<p>Prints the balance of an address or a public key</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "text",
            "optional": false,
            "field": "arg2",
            "description": "<p>address or public_key</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php balance 5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "Balance: 2,487",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "block",
    "title": "Block",
    "name": "block",
    "group": "UTIL",
    "description": "<p>Returns a specific block</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "text",
            "optional": false,
            "field": "arg2",
            "description": "<p>block id</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php block 4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "array(9) {\n [\"id\"]=>\n string(88) \"4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR\"\n [\"generator\"]=>\n string(88) \"5ADfrJUnLefPsaYjMTR4KmvQ79eHo2rYWnKBRCXConYKYJVAw2adtzb38oUG5EnsXEbTct3p7GagT2VVZ9hfVTVn\"\n [\"height\"]=>\n int(16833)\n [\"date\"]=>\n int(1519312385)\n [\"nonce\"]=>\n string(41) \"EwtJ1EigKrLurlXROuuiozrR6ICervJDF2KFl4qEY\"\n [\"signature\"]=>\n string(97) \"AN1rKpqit8UYv6uvf79GnbjyihCPE1UZu4CGRx7saZ68g396yjHFmzkzuBV69Hcr7TF2egTsEwVsRA3CETiqXVqet58MCM6tu\"\n [\"difficulty\"]=>\n string(8) \"61982809\"\n [\"argon\"]=>\n string(68) \"$SfghIBNSHoOJDlMthVcUtg$WTJMrQWHHqDA6FowzaZJ+O9JC8DPZTjTxNE4Pj/ggwg\"\n [\"transactions\"]=>\n int(0)\n}",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "block-time",
    "title": "Block-time",
    "name": "block_time",
    "group": "UTIL",
    "description": "<p>Shows the block time of the last 100 blocks</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php block-time",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "16830 -> 323\n...\n16731 -> 302\nAverage block time: 217 seconds",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "blocks",
    "title": "Blocks",
    "name": "blocks",
    "group": "UTIL",
    "description": "<p>Prints the id and the height of the blocks &gt;=arg2, max 100 or arg3</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "number",
            "optional": false,
            "field": "arg2",
            "description": "<p>Starting height</p>"
          },
          {
            "group": "Parameter",
            "type": "number",
            "optional": true,
            "field": "arg3",
            "description": "<p>Block Limit</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php blocks 10800 5",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "10801   2yAHaZ3ghNnThaNK6BJcup2zq7EXuFsruMb5qqXaHP9M6JfBfstAag1n1PX7SMKGcuYGZddMzU7hW87S5ZSayeKX\n10802   wNa4mRvRPCMHzsgLdseMdJCvmeBaCNibRJCDhsuTeznJh8C1aSpGuXRDPYMbqKiVtmGAaYYb9Ze2NJdmK1HY9zM\n10803   3eW3B8jCFBauw8EoKN4SXgrn33UBPw7n8kvDDpyQBw1uQcmJQEzecAvwBk5sVfQxUqgzv31JdNHK45JxUFcupVot\n10804   4mWK1f8ch2Ji3D6aw1BsCJavLNBhQgpUHBCHihnrLDuh8Bjwsou5bQDj7D7nV4RsEPmP2ZbjUUMZwqywpRc8r6dR\n10805   5RBeWXo2c9NZ7UF2ubztk53PZpiA4tsk3bhXNXbcBk89cNqorNj771Qu4kthQN5hXLtu1hzUnv7nkH33hDxBM34m",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "check-address",
    "title": "Check-Address",
    "name": "check_address",
    "group": "UTIL",
    "description": "<p>Checks a specific address for validity</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "text",
            "optional": false,
            "field": "arg2",
            "description": "<p>block id</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php check-address 4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "The address is valid",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "clean",
    "title": "Clean",
    "name": "clean",
    "group": "UTIL",
    "description": "<p>Cleans the entire database</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php clean",
        "type": "cli"
      }
    ],
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "clean-blacklist",
    "title": "Clean-Blacklist",
    "name": "clean_blacklist",
    "group": "UTIL",
    "description": "<p>Removes all the peers from blacklist</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php clean-blacklist",
        "type": "cli"
      }
    ],
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "current",
    "title": "Current",
    "name": "current",
    "group": "UTIL",
    "description": "<p>Prints the current block in var_dump</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php current",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "array(9) {\n [\"id\"]=>\n string(88) \"4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR\"\n [\"generator\"]=>\n string(88) \"5ADfrJUnLefPsaYjMTR4KmvQ79eHo2rYWnKBRCXConYKYJVAw2adtzb38oUG5EnsXEbTct3p7GagT2VVZ9hfVTVn\"\n [\"height\"]=>\n int(16833)\n [\"date\"]=>\n int(1519312385)\n [\"nonce\"]=>\n string(41) \"EwtJ1EigKrLurlXROuuiozrR6ICervJDF2KFl4qEY\"\n [\"signature\"]=>\n string(97) \"AN1rKpqit8UYv6uvf79GnbjyihCPE1UZu4CGRx7saZ68g396yjHFmzkzuBV69Hcr7TF2egTsEwVsRA3CETiqXVqet58MCM6tu\"\n [\"difficulty\"]=>\n string(8) \"61982809\"\n [\"argon\"]=>\n string(68) \"$SfghIBNSHoOJDlMthVcUtg$WTJMrQWHHqDA6FowzaZJ+O9JC8DPZTjTxNE4Pj/ggwg\"\n [\"transactions\"]=>\n int(0)\n}",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "delete-peer",
    "title": "Delete-peer",
    "name": "delete_peer",
    "group": "UTIL",
    "description": "<p>Removes a peer from the peerlist</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "text",
            "optional": false,
            "field": "arg2",
            "description": "<p>Peer's hostname</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php delete-peer http://peerX.steroid.io",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "Peer removed",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "get-address",
    "title": "Get-Address",
    "name": "get_address",
    "group": "UTIL",
    "description": "<p>Converts a public key into an address</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "text",
            "optional": false,
            "field": "arg2",
            "description": "<p>public key</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php get-address PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwQr8cE5s6APWAE1SWAmH6NM1nJTryBURULEsifA2hLVuW5GXFD1XU6s6REG1iPK7qGaRDkGpQwJjDhQKVoSVkSNp",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "mempool",
    "title": "Mempool",
    "name": "mempool",
    "group": "UTIL",
    "description": "<p>Prints the number of transactions in mempool</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php mempool",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "Mempool size: 12",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "peer",
    "title": "Peer",
    "name": "peer",
    "group": "UTIL",
    "description": "<p>Creates a peering session with another node</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "text",
            "optional": false,
            "field": "arg2",
            "description": "<p>The Hostname of the other node</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php peer http://peer1.steroid.io",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "Peering OK",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "peers",
    "title": "Peers",
    "name": "peers",
    "group": "UTIL",
    "description": "<p>Prints all the peers and their status</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php peers",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "http://123.123.123.123   active\n...\nhttp://peer1.steroid.io    active",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "peers-block",
    "title": "Peers-Block",
    "name": "peers_block",
    "group": "UTIL",
    "description": "<p>Prints the current height of all the peers</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php peers-block",
        "type": "cli"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "http://peer5.steroid.io        16849\n...\nhttp://peer10.steroid.io        16849",
          "type": "text"
        }
      ]
    },
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "pop",
    "title": "Pop",
    "name": "pop",
    "group": "UTIL",
    "description": "<p>Cleans the entire database</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "Number",
            "optional": false,
            "field": "arg2",
            "description": "<p>Number of blocks to delete</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php pop 1",
        "type": "cli"
      }
    ],
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "type": "php util.php",
    "url": "recheck-blocks",
    "title": "Recheck-Blocks",
    "name": "recheck_blocks",
    "group": "UTIL",
    "description": "<p>Recheck all the blocks to make sure the blockchain is correct</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "php util.php recheck-blocks",
        "type": "cli"
      }
    ],
    "version": "1.0.1",
    "filename": "./util.php",
    "groupTitle": "UTIL"
  },
  {
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "optional": false,
            "field": "varname1",
            "description": "<p>No type.</p>"
          },
          {
            "group": "Success 200",
            "type": "String",
            "optional": false,
            "field": "varname2",
            "description": "<p>With type.</p>"
          }
        ]
      }
    },
    "type": "",
    "url": "",
    "version": "1.0.1",
    "filename": "./doc/main.js",
    "group": "_github_node_doc_main_js",
    "groupTitle": "_github_node_doc_main_js",
    "name": ""
  }
] });
