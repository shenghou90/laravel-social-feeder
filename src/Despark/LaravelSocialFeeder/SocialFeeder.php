<?php namespace Despark\LaravelSocialFeeder;
use Illuminate\Support\Facades\Log;
use Config;

use Facebook;

class SocialFeeder {

    public static function updateTwitterPosts()
    {
        $connection = new \Abraham\TwitterOAuth\TwitterOAuth(
            Config::get('laravel-social-feeder::twitterCredentials.consumerKey'),
            Config::get('laravel-social-feeder::twitterCredentials.consumerSecret'),
            Config::get('laravel-social-feeder::twitterCredentials.accessToken'),
            Config::get('laravel-social-feeder::twitterCredentials.accessTokenSecret')
        );
        $params = array(
            'screen_name' => Config::get('laravel-social-feeder::twitterCredentials.screen_name'),
            'count' => Config::get('laravel-social-feeder::twitterCredentials.limit'),
        );

        $lastTwitterPost = \SocialPost::type('twitter')
            ->latest('published_at')
            ->limit('1')
            ->get()
            ->first();

        if ($lastTwitterPost)
        {
            $params['since_id'] = $lastTwitterPost->social_id;
        }
        try
        {
            $tweets = $connection->get('statuses/user_timeline', $params);
        }
        catch (Exception $e)
        {
            $tweets = array();
        }
        $outputs = array();
        foreach ($tweets as $tweet)
        {
            if ( ! is_object($tweet))
                continue;

            $newPostData = [
                'type' => 'twitter',
                'social_id' => $tweet->id_str,
                'url' => 'https://twitter.com/'.$params['screen_name'].'/status/'.$tweet->id_str,
                'text' => $tweet->text,
                'show_on_page' => 1,
                'author_name' => $tweet->user->name,
                'author_image_url' => $tweet->user->profile_image_url,
                'published_at' => date('Y-m-d H:i:s', strtotime($tweet->created_at)),
            ];

            array_push($outputs, $newPostData);
        }
        return $outputs;
    }

    public static function updateFacebookPosts()
    {
        $pageId = Config::get('laravel-social-feeder::facebookCredentials.pageName');
        $limit = Config::get('laravel-social-feeder::facebookCredentials.limit');

        // Get the name of the logged in user
        $appId = Config::get('laravel-social-feeder::facebookCredentials.appId');
        $appSecret = Config::get('laravel-social-feeder::facebookCredentials.appSecret');

        $url = 'https://graph.facebook.com/' . $pageId . '/feed?fields=full_picture,from{name,picture},message,created_time,id,permalink_url&limit=' . $limit . '&access_token=' . $appId . '|' . $appSecret;

        // Initializing curl
        $ch = curl_init( $url );

        // Configuring curl options
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array('Content-type: application/json')
        );

        // Setting curl options
        curl_setopt_array( $ch, $options );

        // Getting results
        $results = curl_exec($ch); // Getting jSON result string
        $results = json_decode($results);
        $results = $results->data;
        $outputs = array();
        foreach ($results as $post) {
            Log::debug("here SHH1: " . print_r($post, true));
            $message = empty($post->message) ? "" : $post->message;
            $imageUrl = empty($post->full_picture) ? "" : $post->full_picture;
            $newPostData = array(
                'type' => 'facebook',
                'social_id' => $post->id,
                'url' => $post->permalink_url,
                'text' => $message,
                'image_url' => $imageUrl,
                'show_on_page' => 1,
                'published_at' => date('Y-m-d H:i:s', strtotime($post->created_time)),
            );
            array_push($outputs, $newPostData);
        }


        /*commented originally from despark
         * Facebook::setAccessToken(Config::get('laravel-social-feeder::facebookCredentials.accessToken'));

        $pageName = Config::get('laravel-social-feeder::facebookCredentials.pageName');

        $posts = Facebook::object($pageName.'/posts')->get()->all();

        $lastPost = \SocialPost::type('facebook')->orderBy('published_at', 'DESC')->first();

        foreach ($posts as $post)
        {
            $published_at = date('Y-m-d H:i:s', $post->get('created_time')->timestamp);

            if ($lastPost and $lastPost->published_at >= $published_at)
                continue;

            if ( ! $post->get('message'))
                continue;

            $socialId = array_get(explode('_', $post->get('id')), 1);

            $newPostData = array(
                'type' => 'facebook',
                'social_id' => $socialId,
                'url' => 'https://www.facebook.com/'.$pageName.'/posts/'.$socialId,
                'text' => $post->get('message'),
                'image_url' => $post->get('picture'),
                'show_on_page' => 1,
                'published_at' => $published_at,
            );

            $newPostEntity = new \SocialPost;
            $newPostEntity->fill($newPostData)->save();
        }*/

        return $outputs;
    }

    public static function updateInstagramPosts()
    {
        $lastInstagramPost = \SocialPost::type('instagram')->latest('published_at')->get()->first();
        $lastInstagramPostTimestamp = $lastInstagramPost ? strtotime($lastInstagramPost->published_at) : 0;

        //$clientId = Config::get('laravel-social-feeder::instagramCredentials.clientId');
        $userId = Config::get('laravel-social-feeder::instagramCredentials.userId');
        $accessToken = Config::get('laravel-social-feeder::instagramCredentials.accessToken');
        $limit = Config::get('laravel-social-feeder::instagramCredentials.limit');

        $url = 'https://api.instagram.com/v1/users/'.$userId.'/media/recent?access_token=' . $accessToken . "&count=" . $limit;;
        $json = file_get_contents($url);

        $obj = json_decode($json);

        $postsData = $obj->data;
        Log::debug('$postsData: ' . print_r($postsData, true));
        $outputs = array();
        foreach ($postsData as $post)
        {
            if(!is_null($post->caption)) {
                if ($post->caption->created_time <= $lastInstagramPostTimestamp)
                    continue;

                $newPostData = array(
                    'type' => 'instagram',
                    'social_id' => $post->id,
                    'url' => $post->link,
                    'text' => $post->caption->text,
                    'image_url' => $post->images->standard_resolution->url,
                    'show_on_page' => 1,
                    'author_name' => $post->user->username,
                    'author_image_url' => $post->user->profile_picture,
                    'published_at' => date('Y-m-d H:i:s', $post->caption->created_time),
                );
                array_push($outputs, $newPostData);
            }
        }

        return $outputs;
    }
}
