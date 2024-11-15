<?php
// app/Services/MicrosoftGraphService.php
namespace App\Services;

use GuzzleHttp\Client;

class MicrosoftGraphService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function getAccessToken()
    {
        $response = $this->client->post('https://login.microsoftonline.com/' . env('MICROSOFT_TENANT_ID') . '/oauth2/v2.0/token', [
            'form_params' => [
                'client_id' => env('MICROSOFT_CLIENT_ID'),
                'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['access_token'];
    }


    // app/Services/MicrosoftGraphService.php
    public function sendEmail($to, $subject, $body)
    {
        $token = $this->getAccessToken();
        $emailData = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'Text',
                    'content' => $body,
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to,
                        ],
                    ],
                ],
            ]
        ];

        $response = $this->client->post('https://graph.microsoft.com/v1.0/users/' . env('MICROSOFT_CLIENT_ID') . '/sendMail', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $emailData,
        ]);

        return $response->getStatusCode();
    }

}
