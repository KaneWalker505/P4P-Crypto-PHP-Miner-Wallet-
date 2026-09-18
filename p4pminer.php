<?php
// p4p miner.php - Proof Processing & Block Reward Solver
if (!isset($argv[1])) {
    die("Usage: p4pminer <your_p4p_wallet_address>\n");
}

$minerAddress = $argv[1];
$nodeUrl = "https://php-crypto.wasmer.app/?path=";
$mempUrl = "https://php-crypto.wasmer.app/mempool.json";
$chainUrl = "https://php-crypto.wasmer.app/chain.json";


function sha256d($data) {
    return hash('sha256', hash('sha256', $data));
}

echo "Starting miner for address: $minerAddress...\n";

while (true) {
    // 1. Fetch current mempool and chain state
    $mempool = json_decode(@file_get_contents("$mempUrl"), true);
    $chain = json_decode(@file_get_contents("$chainUrl"), true);

    if (empty($mempool)) {
        echo "Mempool empty. Waiting for transactions...\n";
        sleep(5);
        continue;
    }

    $lastmem = end($mempool);
    $lastBlock = end($chain);
    $nextIndex = $lastBlock['index'] + 1;
    $previousHash = $lastBlock['hash'];



    // 3. Solve Block (Find SHA256d valid Hash)
    $nonce = rand(100000, 999999);
    $blockData = $nextIndex . $previousHash . json_encode($lastmem) . $nonce;
    $blockHash = sha256d($blockData);

    $newBlock = [
        'index' => $nextIndex,
        'timestamp' => time(),
        'transactions' => $lastmem,
        'previous_hash' => $previousHash,
        'nonce' => $nonce,
        'hash' => $blockHash,
        'miner' => $minerAddress
    ];
  $calculatedHash = sha256d($newBlock['index'] . $newBlock['previous_hash'] . json_encode($newBlock['transactions']) . $newBlock['nonce']);
  if ($calculatedHash === $newBlock['hash'] && $newBlock['previous_hash'] === $lastBlock['hash'])
  {

    // 4. Submit Solved Block
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($newBlock)
        ]
    ];
    
    $context = stream_context_create($opts);
    $result = file_get_contents("$nodeUrl/submit-block", false, $context);
    
    echo "Block solved ! you earned:p4p coin\nNode Response: $result\n";
    sleep(3);
   }
}

?>

