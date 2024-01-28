<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpClient\HttpClient;

class InBox extends AbstractController
{
	#[Route("/inbox", name: "inbox")]
	public function inbox(): JsonResponse {

		//	Get the POST'ed data
		$request = Request::createFromGlobals();
		$inbox_message = $request->getPayload()->all();

		//	No type? Ignore it
		if ( !isset( $inbox_message["type"] ) ) { 
			// file_put_contents("new.txt",serialize($inbox_message)); 
			die(); 
		}

		//	Get the type
		$inbox_type = $inbox_message["type"];

		//	Not a follow request? Ignore it
		if ( "Follow" != $inbox_type ) { 
			// file_put_contents("notfollow.txt",serialize($inbox_message)); 
			die(); 
		}

		//	Get the parameters
		$inbox_id = $inbox_message["id"];
		$inbox_actor = $inbox_message["actor"];
		$inbox_url = parse_url($inbox_actor, PHP_URL_SCHEME) . "://" . parse_url($inbox_actor, PHP_URL_HOST);
		$inbox_host = parse_url($inbox_actor, PHP_URL_HOST);

		//	Response Message ID
		$guid = bin2hex(random_bytes(16));

		//	Accept message
		$message = [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://location.edent.tel/' . $guid,
			'type'     => 'Accept',
			'actor'    => 'https://location.edent.tel/edent_location',
			'object'   => [
				'@context' => 'https://www.w3.org/ns/activitystreams',
				'id'       => $inbox_id,
				'type'     => $inbox_type,
				'actor'    => $inbox_actor,
				'object'   => 'https://location.edent.tel/edent_location',
			]
		];
		$message_json = json_encode($message);

		//	Where is this being sent?
		$host = $inbox_host;
		// $path = '/users/Edent/inbox';
		$path = parse_url($inbox_actor, PHP_URL_PATH) . "/inbox";
		
		//	Set up signing
		$privateKey = $_ENV["PRIVATE_KEY"];
		$keyId = 'https://location.edent.tel/edent_location#main-key';

		$hash = hash('sha256', $message_json, true);
		$digest = base64_encode($hash);
		$date = date('D, d M Y H:i:s \G\M\T');

		$signer = openssl_get_privatekey($privateKey);
		$stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";
		openssl_sign($stringToSign, $signature, $signer, OPENSSL_ALGO_SHA256);
		$signature_b64 = base64_encode($signature);

		$header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . '"';

		//	Header for POST reply
		$headers = array(
			        "Host: {$host}",
			        "Date: {$date}",
			      "Digest: SHA-256={$digest}",
			   "Signature: {$header}",
			"Content-Type: application/activity+json",
			      "Accept: application/activity+json",
		);
	
		file_put_contents("follow.txt",print_r($message_json, true));
		file_put_contents("headers.txt",print_r($headers, true));

		// Specify the URL of the remote server
		$remoteServerUrl = $inbox_actor . "/inbox";

		file_put_contents("remote.txt", $remoteServerUrl);

		//	POST the message and header to the requester's inbox
		$ch = curl_init($remoteServerUrl);

		$curl_error_log = fopen(dirname(__FILE__).'/curlerr.txt', 'w');

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $message_json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_STDERR, $curl_error_log);
	
		$response = curl_exec($ch);
		if(curl_errno($ch)) {
			file_put_contents("error.txt",  curl_error($ch) );
		} else {
			file_put_contents("curl.txt", $response);
		}
		curl_close($ch);

		// //	Send the response
		// $client = HttpClient::create();
		// file_put_contents("client.txt", serialize($client));

		// // Send the POST request
		// $send = $client->request('POST', $remoteServerUrl, [
		// 	'headers' => $headers,
		// 	'json' => $message, // Use 'json' option to automatically encode data as JSON
		// ]);

		// // Get the response content
		// file_put_contents("send.txt",serialize($send->toArray()));

		// $content = $send->getContent();
		// file_put_contents("content.txt",serialize($content));

		//	Render the page
		$response = new JsonResponse($message);	
		$response->headers->add($headers);
		return $response;
	}
}
