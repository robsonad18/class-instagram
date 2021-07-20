<?php

namespace App\Controller\Integration;

use Exception;
use PDOException;
use stdClass;

/**
 * Responsavel pela ação e integraçao com instagram
 * @package App
 */
class Instagram
{
	private string $urlApi   		= 'https://api.instagram.com/v1/';

	private string $oauthUrl 		= 'https://api.instagram.com/oauth/authorize';

	private string $oauthTokenUrl   = 'https://api.instagram.com/oauth/access_token';

	private string $apiKey			= '';

	private string $apiSecret		= '';

	private string $callBackUrl		= '';

	private string $accessToken     = '';

	private array $listScopes       = ['basic', 'likes', 'comments', 'relationships', 'user_profile', 'user_media'];

	private array $listActions		= ['follow', 'unfollow', 'block', 'unblock', 'approve', 'deny'];

	private $xRateLimitRemaining;

	private bool $signedheader	    = false;

	/**
	 * Construtor da classe
	 * @param array $config 
	 * @return void 
	 */
	public function __construct(array $config)
	{
		$this->apiKey 		= $config['apiKey'];
		$this->apiSecret	= $config['apiSecret'];
		$this->callBackUrl  = $config['callback'];
	}


	/**
	 * Get the liked photos of a user.
	 *
	 * @param int $limit Limit of returned results
	 *
	 * @return mixed
	 */
	public function getUserLikes($limit = 0)
	{
		$params = [];
		if ($limit > 0) {
			$params['count'] = $limit;
		}
		return $this->request(
			'users/self/media/liked',
			true,
			$params,
			'GET'
		);
	}

	/**
	 * Get the list of users this user follows
	 *
	 * @param int|string $id Instagram user ID.
	 * @param int $limit Limit of returned results
	 *
	 * @return mixed
	 */
	public function getUserFollows($id = 'self', $limit = 0)
	{
		$params = array();

		if ($limit > 0) {
			$params['count'] = $limit;
		}

		return $this->request(
			'users/' . $id . '/follows',
			true,
			$params,
			'GET'
		);
	}

	/**
	 * Get the list of users this user is followed by.
	 *
	 * @param int|string $id Instagram user ID
	 * @param int $limit Limit of returned results
	 *
	 * @return mixed
	 */
	public function getUserFollower($id = 'self', $limit = 0)
	{
		$params = array();

		if ($limit > 0) {
			$params['count'] = $limit;
		}

		return $this->request(
			'users/' . $id . '/followed-by',
			true,
			$params,
			'GET'
		);
	}

	/**
	 * Get information about a relationship to another user.
	 *
	 * @param int $id Instagram user ID
	 *
	 * @return mixed
	 */
	public function getUserRelationship($id)
	{
		return $this->request(
			'users/' . $id . '/relationship',
			true,
			null,
			'GET'
		);
	}

	/**
	 * Get the value of X-RateLimit-Remaining header field.
	 *
	 * @return int X-RateLimit-Remaining API calls left within 1 hour
	 */
	public function getRateLimit()
	{
		return $this->xRateLimitRemaining;
	}




	/**
	 * Modify the relationship between the current user and the target user.
	 *
	 * @param string $action Action command (follow/unfollow/block/unblock/approve/deny)
	 * @param int $user Target user ID
	 *
	 * @return mixed
	 *
	 * @throws \MetzWeb\Instagram\InstagramException
	 */
	public function modifyRelationship($action, $user)
	{
		if (in_array($action, $this->_actions) && isset($user)) {
			return $this->request(
				'users/' . $user . '/relationship',
				true,
				['action' => $action],
				'POST'
			);
		}

		throw new Exception('Error: modifyRelationship() | This method requires an action command and the target user id.');
	}

	/**
	 * Search media by its location.
	 *
	 * @param float $lat Latitude of the center search coordinate
	 * @param float $lng Longitude of the center search coordinate
	 * @param int $distance Distance in metres (default is 1km (distance=1000), max. is 5km)
	 * @param long $minTimestamp Media taken later than this timestamp (default: 5 days ago)
	 * @param long $maxTimestamp Media taken earlier than this timestamp (default: now)
	 *
	 * @return mixed
	 */
	public function searchMedia($lat, $lng, $distance = 1000, $minTimestamp = null, $maxTimestamp = null)
	{
		return $this->request(
			'media/search',
			false,
			[
				'lat' 			=> $lat,
				'lng' 			=> $lng,
				'distance' 		=> $distance,
				'min_timestamp' => $minTimestamp,
				'max_timestamp' => $maxTimestamp
			],
			'GET'
		);
	}

	/**
	 * Get media by its id.
	 *
	 * @param int $id Instagram media ID
	 *
	 * @return mixed
	 */
	public function getMedia($id)
	{
		return $this->request(
			'media/' . $id,
			isset($this->_accesstoken),
			null,
			'GET'
		);
	}

	/**
	 * Get the most popular media.
	 *
	 * @return mixed
	 */
	public function getPopularMedia()
	{
		return $this->request(
			'media/popular',
			false,
			null,
			'GET'
		);
	}

	/**
	 * Search for tags by name.
	 *
	 * @param string $name Valid tag name
	 *
	 * @return mixed
	 */
	public function searchTags($name)
	{
		return $this->request(
			'tags/search',
			false,
			['q' => $name],
			'GET'
		);
	}

	/**
	 * Get info about a tag
	 *
	 * @param string $name Valid tag name
	 *
	 * @return mixed
	 */
	public function getTag($name)
	{
		return $this->request(
			'tags/' . $name,
			false,
			null,
			'GET'
		);
	}

	/**
	 * Get a recently tagged media.
	 *
	 * @param string $name Valid tag name
	 * @param int $limit Limit of returned results
	 *
	 * @return mixed
	 */
	public function getTagMedia($name, $limit = 0)
	{
		$params = array();

		if ($limit > 0) {
			$params['count'] = $limit;
		}

		return $this->request(
			'tags/' . $name . '/media/recent',
			false,
			$params,
			'GET'
		);
	}

	/**
	 * Get a list of users who have liked this media.
	 *
	 * @param int $id Instagram media ID
	 *
	 * @return mixed
	 */
	public function getMediaLikes($id)
	{
		return $this->request(
			'media/' . $id . '/likes',
			true,
			null,
			'GET'
		);
	}

	/**
	 * Get a list of comments for this media.
	 *
	 * @param int $id Instagram media ID
	 *
	 * @return mixed
	 */
	public function getMediaComments($id)
	{
		return $this->request(
			'media/' . $id . '/comments',
			false,
			null,
			'GET'
		);
	}

	/**
	 * Add a comment on a media.
	 *
	 * @param int $id Instagram media ID
	 * @param string $text Comment content
	 *
	 * @return mixed
	 */
	public function addMediaComment($id, $text)
	{
		return $this->request(
			'media/' . $id . '/comments',
			true,
			array('text' => $text),
			'POST'
		);
	}

	/**
	 * Remove user comment on a media.
	 *
	 * @param int $id Instagram media ID
	 * @param string $commentID User comment ID
	 *
	 * @return mixed
	 */
	public function deleteMediaComment($id, $commentID)
	{
		return $this->request('media/' . $id . '/comments/' . $commentID, true, null, 'DELETE');
	}

	/**
	 * Set user like on a media.
	 *
	 * @param int $id Instagram media ID
	 *
	 * @return mixed
	 */
	public function likeMedia($id)
	{
		return $this->request('media/' . $id . '/likes', true, null, 'POST');
	}

	/**
	 * Remove user like on a media.
	 *
	 * @param int $id Instagram media ID
	 *
	 * @return mixed
	 */
	public function deleteLikedMedia($id)
	{
		return $this->request(
			'media/' . $id . '/likes',
			true,
			null,
			'DELETE'
		);
	}



	/**
	 * Get information about a location.
	 *
	 * @param int $id Instagram location ID
	 *
	 * @return mixed
	 */
	public function getLocation($id)
	{
		return $this->request(
			'locations/' . $id,
			false,
			null,
			'GET'
		);
	}

	/**
	 * Get recent media from a given location.
	 *
	 * @param int $id Instagram location ID
	 *
	 * @return mixed
	 */
	public function getLocationMedia($id)
	{
		return $this->request(
			'locations/' . $id . '/media/recent',
			false,
			null,
			'GET'
		);
	}

	/**
	 * Get recent media from a given location.
	 *
	 * @param float $lat Latitude of the center search coordinate
	 * @param float $lng Longitude of the center search coordinate
	 * @param int $distance Distance in meter (max. distance: 5km = 5000)
	 *
	 * @return mixed
	 */
	public function searchLocation($lat, $lng, $distance = 1000)
	{
		return $this->request(
			'locations/search',
			false,
			['lat' => $lat, 'lng' => $lng, 'distance' => $distance],
			'GET'
		);
	}


	/**
	 * Recurso de paginação.
	 *
	 * @param object $obInstagram Objeto Instagram retornado por um método 
	 * @param int $limit Limite de resultados retornados 
	 *
	 * @return mixed
	 *
	 */
	public function pagination($obInstagram, $limit = 0)
	{
		try {
			if (is_object($obInstagram) && !is_null($obInstagram->pagination)) {
				if (!isset($obInstagram->pagination->next_url)) {
					return;
				}

				$apiCall = explode('?', $obInstagram->pagination->next_url);

				if (count($apiCall) < 2) {
					return;
				}

				$endpoint = str_replace($this->urlApi, '', $apiCall[0]);
				$auth     = (strpos($apiCall[1], 'access_token') !== false);

				if (isset($obInstagram->pagination->next_max_id)) {
					return $this->request(
						$endpoint,
						$auth,
						['max_id' => $obInstagram->pagination->next_max_id, 'count' => $limit],
						'GET'
					);
				}
				return $this->request(
					$endpoint,
					$auth,
					['cursor' => $obInstagram->pagination->next_cursor, 'count' => $limit],
					'GET'
				);
			}

			throw new \Exception("Erro: paginação () | Este método não suporta paginação.");
		} catch (\PDOException $e) {
			echo $e->getMessage();
		}
	}


	/**
	 * Retorna url para autentificação do instagram
	 * @param array $scopes 
	 * @return string 
	 * @throws Exception 
	 */
	public function getLoginUrl($scopes = ['basic']): string
	{
		try {
			foreach ($scopes as $value) {
				if (!in_array($value, $this->listScopes)) {
					throw new Exception('Esse escopo não existe ou não é permitido');
				}
			}
			$url = $this->oauthUrl . '?client_id=' . $this->apiKey . '&redirect_uri=' . urlencode($this->callBackUrl) . '&scope=' . implode(',', $scopes) . '&response_type=code';
			return $url;
		} catch (\PDOException $e) {
			echo $e->getMessage();
		}
	}


	/**
	 * Retorna token de acesso
	 * @param string $code 
	 * @param bool $token 
	 * @return mixed 
	 * @throws Exception 
	 */
	public function getOAuthToken(string $code, $token = false)
	{
		$result = $this->requestOauth([
			'grant_type' 		=> 'authorization_code',
			'client_id' 		=> $this->apiKey,
			'client_secret' 	=> $this->apiSecret,
			'redirect_uri' 		=> $this->callBackUrl,
			'code' 				=> $code
		]);

		return !$token ? $result : $result->access_token;
	}


	/**
	 * Seta token de acesso
	 * @param mixed $token 
	 * @return void 
	 */
	public function setAccessToken($token)
	{
		$this->accessToken = is_object($token) ? $token->access_token : $token;
	}


	public function getUserMedia(string $id = 'self', int $limit = 0)
	{
		$params 		 = [];
		$params['count'] = $limit > 0 ? $limit : 0;

		$data 			 = [
			'endpoint'	 => 'users/' . $id . '/media/recent',
			'auth'		 => strlen($this->accessToken),
			'params'	 => $params,
			'method'	 => 'GET'
		];
		return $this->request(
			$data['endpoint'],
			$data['auth'],
			$data['params'],
			$data['method']
		);
	}

	/**
	 * Chamada de autentificação
	 * @param mixed $apiData 
	 * @return mixed 
	 * @throws Exception 
	 */
	private function requestOauth($apiData)
	{
		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->oauthTokenUrl);
			curl_setopt($ch, CURLOPT_POST, count($apiData));
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 90);
			$jsonData = curl_exec($ch);

			if (!$jsonData) {
				throw new \Exception(curl_error($ch));
			}
			curl_close($ch);

			return json_decode($jsonData);
		} catch (\PDOException $e) {
			echo $e->getMessage();
		}
	}



	/**
	 * Cabeçalho da requisição
	 * @param string $endpoint 
	 * @param mixed $authMethod 
	 * @param array $params 
	 * @return string|false 
	 */
	private function signHeader(string $endpoint, $authMethod, array $params)
	{
		$params = is_array($params) ? $params : [];

		if ($authMethod) {
			list($key, $value) = explode('=', substr($authMethod, 1), 2);
			$params[$key] = $value;
		}

		$baseString = '/' . $endpoint;
		ksort($params);
		foreach ($params as $key => $value) {
			$baseString .= '|' . $key . '=' . $value;
		}
		return hash_hmac('sha256', $baseString, $this->apiSecret, false);
	}



	/**
	 * Processa cabeçalhos
	 * @param mixed $headerContent 
	 * @return array 
	 */
	private function processHeaders($headerContent)
	{
		$headers = [];
		foreach (explode("\r\n", $headerContent) as $i => $line) {
			if ($i === 0) {
				$headers['http_code'] = $line;
				continue;
			}

			list($key, $value) = explode(':', $line);
			$headers[$key]     = $value;
		}
		return $headers;
	}


	/**
	 * Cabeçalho assinado
	 * @param bool $signedHeader 
	 * @return void 
	 */
	public function setSignedHeader(bool $signedHeader)
	{
		$this->signedheader = $signedHeader;
	}



	/**
	 * Executa as requisições
	 * @param string $endpoint 
	 * @param bool $auth 
	 * @param mixed $params 
	 * @param string $method 
	 * @return mixed 
	 * @throws Exception 
	 */
	private function request(string $endpoint, bool $auth, $params, string $method)
	{
		try {
			if ($this->accesstoken === null || !isset($this->accessToken)) {
				throw new \Exception($endpoint);
			}
			// Metodo de autentificação
			$authMethod  = !$auth ? '?client_id=' . $this->apiKey : '?access_token=' . $this->accessToken;
			// Parametros string
			$paramString = isset($params) && is_array($params) ? '&' . http_build_query($params) : null;
			// Url da requisição
			$apiCall     = $this->urlApi . $endpoint . $authMethod . (('GET' === $method) ? $paramString : null);

			if ($this->signHeader) {
				$apiCall .= (strstr($apiCall, '?') ? '&' : '?') . 'sig=' . $this->signHeader($endpoint, $authMethod, $params);
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $apiCall);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
			curl_setopt($ch, CURLOPT_TIMEOUT, 90);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, true);

			switch ($method) {
				case 'POST':
					curl_setopt($ch, CURLOPT_POST, count($params));
					curl_setopt($ch, CURLOPT_POSTFIELDS, ltrim($paramString, '&'));
					break;
				case 'DELETE':
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
					break;
			}

			$jsonData = curl_exec($ch);

			list($headerContent, $jsonData) = explode("\r\n\r\n", $jsonData, 2);

			$headers = $this->processHeaders($headerContent);

			$this->xRateLimitRemaining = $headers['X-Ratelimit-Remaining'];

			if (!$jsonData) {
				throw new Exception(curl_error($ch));
			}

			curl_close($ch);

			return json_decode($jsonData);
		} catch (\PDOException $e) {
			echo $e->getMessage();
		}
	}
}
