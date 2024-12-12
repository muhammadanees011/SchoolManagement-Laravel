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
        $response = $this->client->post('https://login.microsoftonline.com/' . config('services.microsoft.tenant') . '/oauth2/v2.0/token', [
            'form_params' => [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['access_token'];
    }


    // app/Services/MicrosoftGraphService.php
    public function sendEmail($to, $subject, $bodyView, $attachmentContent=null, $attachmentName=null, $data = [])
    {
        $token = $this->getAccessToken();
        // return $token;
        $body = view($bodyView, $data)->render();
        $emailData = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body,
                ],
            ]
        ];

        if (is_array($to)) {
            $emailData['message']['toRecipients'] = array_map(function($email) {
                return [
                    'emailAddress' => [
                        'address' => $email,
                    ],
                ];
            }, $to);
        }

        if ($attachmentContent && $attachmentName) {
            $emailData['message']['attachments'] = [
                [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $attachmentName,
                    'contentBytes' => base64_encode($attachmentContent),
                ],
            ];
        }
        
        $response = $this->client->post('https://graph.microsoft.com/v1.0/users/'.config('services.microsoft.ms_email_account').'/sendMail', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $emailData,
        ]);
        // return $response;
        return $response->getStatusCode();
    }

}
