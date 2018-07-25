<?php

    // Config
    $group_id = 0; // Group ID
    $token = "insert_here"; // Service key from config of VK-group
    $service_key = "insert_here"; // Token from VK
    $version = "5.80"; // Version of VK-api

    // Function for making request to VK api
    function apiRequest ($url, $post_data = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($post_data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        $result = json_decode(curl_exec($ch),true);
        curl_close ($ch);

        // Return JSON
        return $result;
    }

    // Get new member in group
    $result = apiRequest("https://api.vk.com/method/groups.getMembers?group_id={$group_id}&access_token={$token}&sort=time_desc&offset=0&count=1&fields=photo_100&version={$version}");
    $last = $result["response"]["users"][0];

    // Getting messages in group wall
    $result = apiRequest("https://api.vk.com/method/wall.get?owner_id=-{$group_id}&filter=owner&count=50&version={$version}&access_token={$service_key}");
    $lastComDate = 0;
    $lastCom = 0;
    $lastLike = 0;
    for($i=1;$i<=50;$i++){
        $id = $result["response"][$i]["id"];

        $result2 = apiRequest("https://api.vk.com/method/wall.getComments?owner_id=-{$group_id}&post_id={$id}&need_likes=0&count=5&sort=desc&version={$version}&access_token={$service_key}");
        // Looking for last comment 
        if ($result2["response"][0]) {
            $j = 5;
            if ($j > $result2["response"][0]) {
                $j = $result2["response"][0];
            }
            for ($j;$j>0;$j--) {
                if ($result2["response"][$j]["from_id"] != "-{$group_id}") {
                    if ($result2["response"][$j]["date"] > $lastComDate) {
                        $lastCom = $result2["response"][$j]["from_id"];
                        $lastComDate = $result2["response"][$j]["date"];
                    }
                    break;
                }
            }
        }

        // Looking for last like
        if ($lastLike == 0 && !isset($result["response"][$i]["is_pinned"])) {
            $result2 = apiRequest("https://api.vk.com/method/likes.getList?type=post&owner_id=-{$group_id}&item_id={$id}&filter=likes&extended=1&offset=0&count=100&version={$version}&access_token={$service_key}");
            if ($result2["response"]["count"]) {
                $rnd = rand(0, $result2["response"]["count"]-1);
                $lastLike = $result2["response"]["items"][$rnd]["uid"];
            }
        }
    }

    // Get user info from last like
    $result = apiRequest("https://api.vk.com/method/users.get?user_ids={$lastLike}&sort=time_desc&offset=0&count=1&fields=photo_100&version={$version}&access_token={$service_key}");
    $lastLike = $result["response"][0];

    if ($lastCom) {
        // If somebody make comment, get user info
        $waiter = false;
        $result = apiRequest("https://api.vk.com/method/users.get?user_ids={$lastCom}&sort=time_desc&offset=0&count=1&fields=photo_100&version={$version}&access_token={$service_key}");
        $lastCom = $result["response"][0];
    }else{
        // If no comments - insert standart image
        $waiter = true;
        $lastCom = $lastLike;
        $lastCom["first_name"] = "Ждём ваших";
        $lastCom["last_name"] = "Комментариев";
    }

    // Save user images
    $file = file_get_contents($last["photo_100"]);
    $f = fopen("img/photo1.jpg", "w");
    fputs($f, $file);
    fclose($f);

    $file = file_get_contents($lastLike["photo_100"]);
    $f = fopen("img/photo2.jpg", "w");
    fputs($f, $file);
    fclose($f);

    if (!$waiter) {
        $file = file_get_contents($lastCom["photo_100"]);
        $f = fopen("img/photo3.jpg", "w");
        fputs($f, $file);
        fclose($f);
    }

    // Create new cover
    $im = imagecreatetruecolor(1590, 400);
    $photo1 = imagecreatefromjpeg("img/photo1.jpg");
    $photo2 = imagecreatefromjpeg("img/photo2.jpg");
    if (!$waiter) {
        $photo3 = imagecreatefromjpeg("img/photo3.jpg");
    }else{
        $photo3 = imagecreatefromjpeg("img/waiter.jpg");
    }
    $banner = imagecreatefrompng("img/vk.png");
    imageAlphaBlending($banner, true);
    imageSaveAlpha($banner, true);

    // Insert into cover user images
    // Newby
    imagecopyresampled($im, $photo1, 279, 276, 0, 0, 113, 113, 100, 100);
    // Like
    imagecopyresampled($im, $photo2, 649, 275, 0, 0, 113, 113, 100, 100);
    // Comment
    imagecopyresampled($im, $photo3, 1018, 276, 0, 0, 113, 113, 100, 100);

    // Insert cover-template
    imagecopyresampled($im, $banner, 0, 0, 0, 0, 1590, 400, 1590, 400);
    $color = ImageColorAllocate($im, 255, 255, 255);

    // Insert user names to cover
    // Newby
    imagettftext($im, 19, 0, 399, 343, $color, "font/MyriadProBold.ttf", $last["first_name"]);
    imagettftext($im, 19, 0, 399, 367, $color, "font/MyriadProBold.ttf", $last["last_name"]);
    // Like
    imagettftext($im, 19, 0, 769, 343, $color, "font/MyriadProBold.ttf", $lastLike["first_name"]);
    imagettftext($im, 19, 0, 769, 367, $color, "font/MyriadProBold.ttf", $lastLike["last_name"]);
    // Comment
    imagettftext($im, 19, 0, 1138, 343, $color, "font/MyriadProBold.ttf", $lastCom["first_name"]);
    imagettftext($im, 19, 0, 1138, 367, $color, "font/MyriadProBold.ttf", $lastCom["last_name"]);

    // Save cover and destroy other images objects
    imagepng($im, "img/cover.png");
    imagedestroy($im);
    imagedestroy($photo1);
    imagedestroy($photo2);
    imagedestroy($photo3);
    imagedestroy($banner);

    // Get url for upload cover
    $upload_url = file_get_contents("https://api.vk.com/method/photos.getOwnerCoverPhotoUploadServer?group_id={$group_id}&crop_x2=1590&crop_y2=400&access_token={$token}&version={$version}");
    $url = json_decode($upload_url)->response->upload_url;

    // Prepare data for send to server
    $cover_path = dirname(__FILE__)."/img/cover.png";
    $post_data = array("photo" => "@".$cover_path);
    // Send image to VK
    $result = apiRequest($url, $post_data);

    // Save changes
    $save = file_get_contents("https://api.vk.com/method/photos.saveOwnerCoverPhoto?hash=".$result["hash"]."&photo=".$result["photo"]."&access_token={$token}&version={$version}");

    header("Content-Type: image/png");
    imagepng($im);
    imagedestroy($im);
    imagedestroy($photo);
    imagedestroy($banner);

?>