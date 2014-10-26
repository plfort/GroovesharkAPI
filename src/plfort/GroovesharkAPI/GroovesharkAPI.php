<?php
namespace plfort\GroovesharkAPI;

/**
 *
 *
 * Grooveshark API Class
 *
 * @author James Hartig
 * @copyright 2013
 *            Released under GNU General Public License v3
 *           
 *            Modified by Pierre-Louis FORT
 *            Changes :
 *            - Add GroovesharkException for error handling
 *            - Add namespace
 *            - Remove dns cache
 *           
 *           
 */
class GroovesharkAPI
{

    const API_HOST = "api.grooveshark.com";

    const API_ENDPOINT = "/ws3.php";

    /**
     * Your key and secret will be provided by Grooveshark.
     * Fill them in below or pass them to the constructor.
     */
    private $wsKey = "example";

    private $wsSecret = "1a79a4d60de6718e8e5b326e338ae533";

    protected $sessionID = null;

    protected $country;

    function __construct($key = null, $secret = null, $sessionID = null, $country = null)
    {
        if (empty($key) || empty($secret)) {
            throw new GroovesharkException("GroovesharkAPI class requires a valid key and secret.");
        }
        
        $this->wsKey = $key;
        $this->wsSecret = $secret;
        
        if (! empty($sessionID)) {
            $this->sessionID = $sessionID;
        }
        if (! empty($country)) {
            $this->country = $country;
        }
    }
    
    /*
     * Ping Grooveshark to make sure Pickles is sleeping
     */
    public function pingService()
    {
        return $this->makeCall('pingService', array());
    }

    /**
     * Methods related specifically to sessions
     * Calls require special access.
     */
    
    /*
     * Start a new session and save it in sessionID
     */
    public function startSession()
    {
        $result = $this->makeCall('startSession', array(), 'sessionID', true);
        if (empty($result)) {
            return $result;
        }
        $this->sessionID = $result;
        return $result;
    }
    
    /*
     * Set the current session for use with methods
     */
    public function setSession($sessionID)
    {
        $this->sessionID = $sessionID;
    }
    
    /*
     * Returns the current SessionID This should be stored instead of username/token @deprecated
     */
    public function getSession()
    {
        return $this->sessionID;
    }
    
    /*
     * Logs out any authenticated user from the current session This requires a valid sessionID Can be called statically or dynamically
     */
    public function logout()
    {
        $result = $this->makeCall('logout', array(), 'success', false);
        if (empty($result)) {
            return false;
        }
        return $result;
    }
    
    // backwards-compatible
    // @deprecated
    public function endSession()
    {
        return $this->logout();
    }
    
    /*
     * Returns information about the logged-in user based on the current sessionID
     */
    public function getUserInfo()
    {
        return $this->makeCall('getUserInfo', array(), null, true);
    }
    
    /*
     * Set the current country for use with methods
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }
    
    /*
     * Returns a country object for the given IP. This should be cached since it won't change. Call requires session access. todo: this doesn't match getSession but somehow should...
     */
    public function getCountry($ip = null)
    {
        // filter_var is 5.2+ only
        if (! empty($ip) && ! filter_var($ip, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new GroovesharkException("Invalid IP sent to getCountry! Sent: $ip");
            return false;
        }
        $args = array();
        if (! empty($ip)) {
            $args['ip'] = $ip;
        }
        $country = $this->makeCall('getCountry', $args);
        if (! empty($country)) {
            $this->country = $country;
        }
        return $country;
    }

    /**
     * Methods relating to the logged-in user
     * Calls require session access.
     */
    
    /*
     * Authenticate a user Username can be the user's email or username. Password should be sent unmodified to this method.
     */
    public function authenticate($username, $password)
    {
        if (empty($username) || empty($password)) {
            return array();
        }
        $args = array(
            'login' => $username,
            'password' => $password
        );
        $result = $this->makeCall('authenticate', $args, null, true);
        if (empty($result['UserID'])) {
            return array();
        }
        return $result;
    }
    
    // backwards-compatible
    public function login($username, $password)
    {
        return $this->authenticate($username, $password);
    }
    
    /*
     * Get the logged-in user's playlists Requires a valid sessionID and authenticated user
     */
    public function getUserPlaylists($limit = null)
    {
        $args = array();
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        return $this->makeCall('getUserPlaylists', $args, 'playlists', false);
    }
    
    /*
     * Returns the playlists owned by the given userID
     */
    public function getUserPlaylistsByUserID($userID, $limit = null)
    {
        if (! is_numeric($userID)) {
            return false;
        }
        $args = array(
            'userID' => (int) $userID
        );
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        return $this->makeCall('getUserPlaylistsByUserID', $args, 'playlists', false);
    }
    
    /*
     * Get the logged-in user's library Requires a valid sessionID and authenticated user
     */
    public function getUserLibrary($limit = null)
    {
        $args = array();
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        return $this->makeCall('getUserLibrarySongs', $args, 'songs', false);
    }
    // backwards-compatible version
    public function getUserLibrarySongs($limit = null)
    {
        return self::getUserLibrary($limit);
    }
    
    /*
     * Get the logged-in user's favorites Requires a valid sessionID and authenticated user
     */
    public function getUserFavorites($limit = null)
    {
        $args = array();
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        return $this->makeCall('getUserFavoriteSongs', $args, 'songs', false);
    }
    // backwards-compatible version
    public function getUserFavoriteSongs($limit = null)
    {
        return self::getUserFavoriteSongs($limit);
    }
    
    /*
     * Adds a song to the logged-in user's favorites Requires a valid sessionID and authenticated user
     */
    public function addUserFavoriteSong($songID)
    {
        if (! is_numeric($songID)) {
            return false;
        }
        
        return $this->makeCall('addUserFavoriteSong', array(
            'songID' => (int) $songID
        ), 'success', false);
    }
    
    /*
     * Creates a playlist for the logged-in user
     */
    public function createPlaylist($name, $songIDs = null)
    {
        if (empty($name)) {
            return array();
        }
        if (is_null($songIDs)) {
            $songIDs = array();
        }
        $args = array(
            'name' => $name,
            'songIDs' => $songIDs
        );
        return $this->makeCall('createPlaylist', $args, null, false);
    }
    
    /*
     * Adds a song to the end of a playlist
     */
    public function addSongToPlaylist($playlistID, $songID)
    {
        if (! is_numeric($playlistID) || ! is_numeric($songID)) {
            return false;
        }
        
        // first we need to retrieve playlist songs then we need to set playlist songs
        $songs = self::getPlaylistSongs($playlistID);
        if (! is_array($songs)) {
            return false; // we couldn't process the songs, look for getPlaylistSongs to return error
        }
        $songs[] = $songID;
        
        return $this->setPlaylistSongs($playlistID, $songs);
    }
    
    /*
     * Changes a playlist's songs owned by the logged-in user returns array('success' => boolean)
     */
    public function setPlaylistSongs($playlistID, $songIDs)
    {
        if (! is_numeric($playlistID) || ! is_array($songIDs)) {
            return array(
                'success' => false
            );
        }
        
        $args = array(
            'playlistID' => (int) $playlistID,
            'songIDs' => $songIDs
        );
        return $this->makeCall('setPlaylistSongs', $args, null, false);
    }

    /**
     * Methods relating to artists/albums/songs
     */
    
    /*
     * Retrieves information for the given artistID
     */
    public function getArtistInfo($artistID)
    {
        if (empty($artistID)) {
            return false;
        }
        
        $result = $this->getArtistsInfo(array(
            $artistID
        ));
        
        if (empty($result)) {
            return $result;
        }
        return $result[0];
    }
    
    /*
     * Retrieves information for the given artistIDs Note: not guaranteed to come back in the same order
     */
    public function getArtistsInfo($artistIDs, $returnByIDs = false)
    {
        if (empty($artistIDs)) {
            return array();
        }
        if (! is_array($artistIDs)) {
            $artistIDs = array(
                $artistIDs
            );
        }
        
        $result = $this->makeCall('getArtistsInfo', array(
            'artistIDs' => $artistIDs
        ), 'artists', false);
        if ($returnByIDs) {
            $artistsKeyed = array();
            foreach ($result as $artist) {
                if (! empty($artist['ArtistID'])) {
                    $artistsKeyed[$artist['ArtistID']] = $artist;
                }
            }
            return $artistsKeyed;
        }
        return $result;
    }
    
    /*
     * Returns a songID from the Tinysong Base62 Requires special access.
     */
    public function getSongIDFromTinysongBase62($base)
    {
        if (! preg_match("/^[A-Za-z0-9]+$/", $base)) {
            return false;
        }
        return $this->makeCall('getSongIDFromTinysongBase62', array(
            'base62' => $base
        ), 'songID', false);
    }
    
    /*
     * Returns the Grooveshark URL for a Tinysong Base62 Requires special access.
     */
    public function getSongURLFromTinysongBase62($base)
    {
        if (! preg_match("/^[A-Za-z0-9]+$/", $base)) {
            return false;
        }
        
        // todo: remove this once we everything is forced dynamically
        return $this->makeCall('getSongURLFromTinysongBase62', array(
            'base62' => $base
        ), 'url', false);
    }
    
    /*
     * Returns a Grooveshark URL for the given SongID Requires special access.
     */
    public function getSongURLFromSongID($songID)
    {
        if (! is_numeric($songID)) {
            return false;
        }
        
        return $this->makeCall('getSongURLFromSongID', array(
            'songID' => (int) $songID
        ), 'url', false);
    }
    
    /*
     * Returns metadata about the given songID
     */
    public function getSongInfo($songID)
    {
        if (! is_numeric($songID)) {
            return array();
        }
        
        $result = $this->getSongsInfo(array(
            $songID
        ));
        if (empty($result)) {
            return $result;
        }
        return $result[0];
    }
    
    /*
     * Returns metadata about multiple songIDs Note: not guaranteed to come back in the same order if returnByIDs is true, the songs are returned in a array keyed by songID
     */
    public function getSongsInfo($songIDs, $returnByIDs = false)
    {
        if (empty($songIDs)) {
            return array();
        }
        if (! is_array($songIDs)) {
            $songIDs = array(
                $songIDs
            );
        }
        
        $result = $this->makeCall('getSongsInfo', array(
            'songIDs' => $songIDs
        ), 'songs', false);
        if (empty($result)) {
            return $result;
        }
        if ($returnByIDs) {
            $songsKeyed = array();
            foreach ($result as $song) {
                if (! empty($song['SongID'])) {
                    $songsKeyed[$song['SongID']] = $song;
                }
            }
            return $songsKeyed;
        }
        return $result;
    }
    
    /*
     * Returns metadata about the given albumID
     */
    public function getAlbumInfo($albumID)
    {
        if (! is_numeric($albumID)) {
            return array();
        }
        
        $result = $this->getAlbumsInfo(array(
            $albumID
        ));
        
        if (empty($result)) {
            return $result;
        }
        return $result[0];
    }
    
    /*
     * Returns metadata about multiple albumIDs Note: not guaranteed to come back in the same order if returnByIDs is true, the songs are returned in a array keyed by AlbumID
     */
    public function getAlbumsInfo($albumIDs, $returnByIDs = false)
    {
        if (empty($albumIDs)) {
            return array();
        }
        if (! is_array($albumIDs)) {
            $albumIDs = array(
                $albumIDs
            );
        }
        $result = $this->makeCall('getAlbumsInfo', array(
            'albumIDs' => $albumIDs
        ), 'albums', false);
        if (empty($result)) {
            return $result;
        }
        if ($returnByIDs) {
            $albumsKeyed = array();
            foreach ($result as $album) {
                if (! empty($album['AlbumID'])) {
                    $albumsKeyed[$album['AlbumID']] = $album;
                }
            }
            return $albumsKeyed;
        }
        return $result;
    }
    
    /*
     * Get songs on a given albumID
     */
    public function getAlbumSongs($albumID, $limit = null)
    {
        if (! is_numeric($albumID)) {
            return array();
        }
        
        $args = array(
            'albumID' => (int) $albumID
        );
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        return $this->makeCall('getAlbumSongs', $args, 'songs', false);
    }
    
    /*
     * Get songs on a given playlistID
     */
    public function getPlaylistSongs($playlistID, $limit = null)
    {
        if (! is_numeric($playlistID)) {
            return array();
        }
        
        $args = array(
            'playlistID' => (int) $playlistID
        );
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        
        return $this->makeCall('getPlaylistSongs', $args, 'songs', false);
    }
    
    /*
     * Returns whether a given songID exists or not. Returns array('exists' => boolean)
     */
    public function getDoesSongExist($songID)
    {
        $return = array(
            'exists' => false
        );
        if (! is_numeric($songID)) {
            return $return;
        }
        
        $result = $this->makeCall('getDoesSongExist', array(
            'songID' => $songID
        ), false, false);
        if (isset($result['result'])) {
            $return['exists'] = $result['result'];
        }
        return $return;
    }
    
    /*
     * Returns whether a given artistID exists or not. Returns array('exists' => boolean)
     */
    public function getDoesArtistExist($artistID)
    {
        $return = array(
            'exists' => false
        );
        if (! is_numeric($artistID)) {
            return $return;
        }
        
        $result = $this->makeCall('getDoesArtistExist', array(
            'artistID' => $artistID
        ), false, false);
        if (isset($result['result'])) {
            $return['exists'] = $result['result'];
        }
        return $return;
    }
    
    /*
     * Returns whether a given albumID exists or not. Returns array('exists' => boolean)
     */
    public function getDoesAlbumExist($albumID)
    {
        $return = array(
            'exists' => false
        );
        if (! is_numeric($albumID)) {
            return $return;
        }
        
        $result = $this->makeCall('getDoesAlbumExist', array(
            'albumID' => $albumID
        ), false, false);
        if (isset($result['result'])) {
            $return['exists'] = $result['result'];
        }
        return $return;
    }
    
    /*
     * Returns a list of an artistID's albums Optionally allows you to get only the verified albums
     */
    public function getArtistAlbums($artistID, $verified = false)
    {
        if (! is_numeric($artistID)) {
            return false;
        }
        
        $args = array(
            'artistID' => (int) $artistID
        );
        if ($verified) {
            $result = $this->makeCall('getArtistVerifiedAlbums', $args, 'albums', false);
        } else {
            $result = $this->makeCall('getArtistAlbums', $args, 'albums', false);
        }
        return $result;
    }
    
    /*
     * Alias class for getArtistAlbums with verified true
     */
    public function getArtistVerifiedAlbums($artistID)
    {
        return $this->getArtistAlbums($artistID, true);
    }
    
    /*
     * Returns the top 100 songs for an artistID
     */
    public function getArtistPopularSongs($artistID)
    {
        if (! is_numeric($artistID)) {
            return false;
        }
        
        return $this->makeCall('getArtistPopularSongs', array(
            'artistID' => (int) $artistID
        ), 'songs', false);
    }
    
    /*
     * Returns a list of today's popular songs
     */
    public function getPopularSongsToday($limit = null)
    {
        $args = array();
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        
        return $this->makeCall('getPopularSongsToday', $args, 'songs', false);
    }
    
    /*
     * Returns a list of today's popular songs
     */
    public function getPopularSongsMonth($limit = null)
    {
        $args = array();
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        
        return $this->makeCall('getPopularSongsMonth', $args, 'songs', false);
    }
    
    /*
     * Get search results for a song This method is access controlled.
     */
    public function getSongSearchResults($query, $country = null, $limit = null, $page = null)
    {
        if (empty($query)) {
            return array();
        }
        // todo: remove check
        if (empty($this->country) && empty($country)) {
            throw new GroovesharkException("getSongSearchResults requires a country. Make sure you call getCountry or setCountry!");
            // return array();
        }
        if (empty($country)) {
            $country = $this->country;
        }
        
        $args = array(
            'query' => $query,
            'country' => $country
        );
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        if (! empty($page)) {
            $page = (int) $page;
            if (isset($limit)) {
                $offset = ($page - 1) * (int) $limit;
            } else {
                $offset = ($page - 1) * 100;
            }
            if ($offset > 0) {
                $args['offset'] = $offset;
            }
        }
        
        return $this->makeCall('getSongSearchResults', $args, 'songs', false);
    }
    
    /*
     * Get search results for an artist name This method is access controlled. To see if there is more than x artists, send a limit of x+1.
     */
    public function getArtistSearchResults($query, $limit = null, $page = null)
    {
        if (empty($query)) {
            return array();
        }
        
        $args = array(
            'query' => $query
        );
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        if (! empty($page)) {
            $args['page'] = (int) $page;
        }
        
        return $this->makeCall('getArtistSearchResults', $args, 'artists', false);
    }
    
    /*
     * Get search results for an album name This method is access controlled. To see if there is more than x albums, send a limit of x+1.
     */
    public function getAlbumSearchResults($query, $limit = null, $page = null)
    {
        if (empty($query)) {
            return array();
        }
        
        $args = array(
            'query' => $query
        );
        if (! empty($limit)) {
            $args['limit'] = (int) $limit;
        }
        if (! empty($page)) {
            $args['page'] = (int) $page;
        }
        
        return $this->makeCall('getAlbumSearchResults', $args, 'albums', false);
    }
    
    /*
     * Get a stream mp3 url for a given songID. This can be used to stream the song once to an mp3-compatible player. This method is access controlled.
     */
    public function getStreamKeyStreamServer($songID, $lowBitrate = false)
    {
        if (empty($songID)) {
            return array();
        }
        if (empty($this->country)) {
            throw new GroovesharkException("getStreamKeyStreamServer requires a country. Make sure you call getCountry or setCountry!");
            return array();
        }
        $args = array(
            'songID' => (int) $songID,
            'country' => $this->country
        );
        if ($lowBitrate) {
            $args['lowBitrate'] = true;
        }
        $result = $this->makeCall('getStreamKeyStreamServer', $args, null, false);
        if (empty($result) || empty($result['StreamKey'])) {
            return array();
        }
        $serverURL = parse_url($result['url']);
        $result['StreamServerHostname'] = $serverURL['host'];
        return $result;
    }
    
    /*
     * Mark an existing streamKey/streamServerID as being played for >30 seconds This should be called after 30 seconds of listening, not just at the 30 seconds mark. returns array('success' => boolean)
     */
    public function markStreamKeyOver30Secs($streamKey, $streamServerID)
    {
        if (empty($streamKey) || empty($streamServerID)) {
            return array(
                'success' => false
            );
        }
        $args = array(
            'streamKey' => $streamKey,
            'streamServerID' => $streamServerID
        );
        return $this->makeCall('markStreamKeyOver30Secs', $args, null, false);
    }
    
    /*
     * Marks an song stream as completed Complete is defined as: Played for greater than or equal to 30 seconds, and having reached the last second either through seeking or normal playback. returns array('success' => boolean)
     */
    public function markSongComplete($songID, $streamKey, $streamServerID)
    {
        if (empty($songID) || empty($streamKey) || empty($streamServerID)) {
            return false;
        }
        $args = array(
            'songID' => (int) $songID,
            'streamKey' => $streamKey,
            'streamServerID' => $streamServerID
        );
        return $this->makeCall('markSongComplete', $args, null, false);
    }
    
    /*
     * Make a call to the Grooveshark API
     */
    protected function makeCall($method, $args = array(), $resultKey = null, $https = false)
    {

        if(empty($this->sessionID)){
            throw new GroovesharkException("$method requires a valid sessionID: '{$this->sessionID}' is not valid.");
        }
        
        $payload = array(
            'method' => $method,
            'parameters' => $args,
            'header' => array(
                'wsKey' => $this->wsKey,
                'sessionID'=>$this->sessionID
            )
        );
        
        $c = curl_init();
        $postData = json_encode($payload);
        curl_setopt($c, CURLOPT_POST, 1);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postData);
        
        if ($https) {
            $scheme = "https://";
        } else {
            $scheme = "http://";
        }
        $sig = $this->createMessageSig($postData);
        $url = $scheme . self::API_HOST . self::API_ENDPOINT . "?sig=$sig";
        curl_setopt($c, CURLOPT_URL, $url);
        
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($c, CURLOPT_TIMEOUT, 6);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_USERAGENT, 'fastest963-GroovesharkAPI-PHP-' . $this->wsKey);
        $return = curl_exec($c);
        $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($httpCode != 200) {
            throw new GroovesharkException("Unexpected return code from Grooveshark API. Code $httpCode.");
        }
        
        $result = json_decode($return, true);
        if (is_null($result) || empty($result['result'])) {
            if (! empty($result['errors'])) {
                throw new GroovesharkException($result['errors']);
            }
            return false;
        } else{
            if (! empty($resultKey)) {
                if (! isset($result['result'][$resultKey])) {
                    return false;
                }
                $result = $result['result'][$resultKey];
            } else{
                if ($resultKey !== false) {
                    $result = $result['result'];
                } 
            }

        }

        return $result;
    }
    
    /*
     * Creates the message signature before sending to Grooveshark
     */
    private function createMessageSig($params)
    {
        return hash_hmac('md5', $params, $this->wsSecret);
    }
}

