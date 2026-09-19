<?php
// node.php - decentralized Daemon Node
define('CHAIN_FILE', 'chain.json');
define('MEMPOOL_FILE', 'mempool.json');
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
$node = "https://php-crypto.wasmer.app/";

function sendNode(array $newBlock) {
    // forward our Miners solved blocks to main node
    // 4. Submit Solved Block
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($newBlock)
        ]
    ];

    $context = stream_context_create($opts);
    $result = file_get_contents("$node/?path=submit-block", false, $context);

}


// Initialize local storage files
if (!file_exists(CHAIN_FILE)) {
    $genesisBlock = [
        'index' => 0,
        'timestamp' => time(),
        'transactions' => [],
        'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000',
        'nonce' => 0,
        'hash' => 'genesis_hash'
    ];
    file_put_contents(CHAIN_FILE, json_encode([$genesisBlock], JSON_PRETTY_PRINT));
}

 $fileData = file_get_contents($node . "chain.json");
 if ($fileData !== false) {
    file_put_contents(CHAIN_FILE, $fileData);
 }



if (!file_exists(MEMPOOL_FILE)) {
    file_put_contents(MEMPOOL_FILE, json_encode([], JSON_PRETTY_PRINT));
}

 $fileData = file_get_contents($node . "mempool.json");
 if ($fileData !== false) {
    file_put_contents(MEMPOOL_FILE, $fileData);
 }



// Function helper for SHA256d
function sha256d($data) {
    return hash('sha256', hash('sha256', $data));
}

// Minimal Built-in HTTP Server
$sip = getHostByName(getHostName());
//$server = stream_socket_server("tcp://" . $sip . ":3333", $errno, $errstr);
//if (!$server) die("Error starting node: $errstr\n");

//echo "PHP Crypto Node running on this domain...\n";

while (true) {
    $request = file_get_contents('php://input');

    $path = $_GET['path'];

    $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

    // Extract JSON payload
    $body = '';
   //$body= json_decode($request, true);
    if (strpos($request, "\r\n\r\n") !== false) {
        //$parts = explode("\r\n\r\n", $request, 2);
        //$body = $parts[1] ?? '';
        $body= json_decode($request, true);


    }

    $response = ['status' => 'error', 'message' => 'P4P Crypto Node running on this domain PHP based crypto-currency by kanewalker505 icuruok'];

    if ($method === 'GET' && $path === '/mempool') {
        $response = json_decode(file_get_contents(MEMPOOL_FILE), true);
    }
    elseif ($method === 'GET' && $path === '/chain') {
        $response = json_decode(file_get_contents(CHAIN_FILE), true);
    }
    elseif ($method === 'POST' && $path === '/tx') {
        $tx = json_decode($request, true);
        if ($tx && isset($tx['from'], $tx['to'], $tx['amount'], $tx['key'])) {

        $chain = json_decode(file_get_contents(CHAIN_FILE), true);


        $balance = 0.0;
        if ($chain) {
            foreach ($chain as $block) {
                foreach ($block['transactions'] as $tg) {
                    if ($tg['to'] === $tx['from']) $balance += $tg['amount'];
                    if ($tg['from'] === $tx['from']) $balance -= $tg['amount'];
                }
            }
        }
        if ($balance < $tx['amount']) {
          $response = ['status' => 'error', 'message' => 'Transaction failed not enough p4p coin'];
          $payload = json_encode($response);
          echo $payload;
          die();
        }

         // verify private key is valid to sender
         $taddress = '0x' . substr(sha256d($tx['key']), 0, 20);
         if ($taddress == $tx['from']) {
            //encrypt privatekey for mempool
            $tx['key'] = base64_encode($tx['key']);

            // Store the cipher method
            $ciphering = "AES-128-CTR";
            $iv_length = openssl_cipher_iv_length($ciphering);

            // Non-NULL Initialization Vector for encryption
            $encryption_iv = '1234567891011121';

            // Store the encryption key
            $encryption_key = "mycustomp4pnode";

            // Use openssl_encrypt() function to encrypt the data
            $tx['key'] = openssl_encrypt($tx['key'], $ciphering,
            $encryption_key, 0, $encryption_iv);

            $mempool = json_decode(file_get_contents(MEMPOOL_FILE), true);
            $mempool[] = $tx;
            file_put_contents(MEMPOOL_FILE, json_encode($mempool, JSON_PRETTY_PRINT));
            $response = ['status' => 'success', 'message' => 'Transaction added to mempool'];
         }
        }
    }
    elseif ($method === 'POST' && $path === '/submit-block') {
        $block = json_decode($request, true);
        $chain = json_decode(file_get_contents(CHAIN_FILE), true);
        $lastBlock = end($chain);

       $ttx[] = $block['transactions'];
       foreach ($ttx as $tx) {
        // Validate hash block and reward miner

         $minerAddress = $block['miner'];
         $fee = $tx['amount'] * 0.05; // 5% fee allo>
         $netAmount = $tx['amount'] - $fee;

         $totalReward += $fee;

         $blockTxs[] = [
            'from' => $tx['from'],
            'to' => $tx['to'],
            'amount' => $netAmount
         ];
      // Append Coinbase/Reward Transaction to Miner
         $blockTxs[] = [
            'from' => 'COINBASE',
            'to' => $minerAddress,
            'amount' => $totalReward
         ];


       }
        $block['transactions'] = $blockTxs;
        if ($block['hash'] && $block['previous_hash'] === $lastBlock['hash']) {
            $chain[] = $block;
            file_put_contents(CHAIN_FILE, json_encode($chain, JSON_PRETTY_PRINT));
            // Clear confirmed transactions from mempool
            file_put_contents(MEMPOOL_FILE, json_encode([], JSON_PRETTY_PRINT));
            $response = ['status' => 'success', 'message' => 'Block accepted and added to chain'];
            sendNode(json_decode($request, true)); //send to main node
        } else {
            $response = ['status' => 'error', 'message' => 'Invalid block submission ' . $tx['key']];
        }
    }

    $payload = json_encode($response);
    $httpResponse = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n\r\n" . $payload;
    echo $payload;
    die();
    //fwrite($socket, $httpResponse);
    //fclose($socket);
}
?>

