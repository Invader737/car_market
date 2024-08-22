<?php

function translateText($text, $targetLang = 'EN')
{
    $apiKey = '63cd0fa0-09bf-4cb6-ba27-da1e05af566a:fx';
    $url = 'https://api-free.deepl.com/v2/translate';

    $data = [
        'auth_key' => $apiKey,
        'text' => $text,
        'target_lang' => $targetLang
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);

    curl_close($ch);

    $responseDecoded = json_decode($response, true);

    if (isset($responseDecoded['translations'][0]['text'])) {
        return $responseDecoded['translations'][0]['text'];
    } else {
        return 'Error: Could not translate text.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text'])) {
    $originalText = $_POST['text'];
    $translatedText = translateText($originalText, 'EN'); // Перевод на английский

    return htmlspecialchars($translatedText);
}
