<?php
include('index.php');

        $playlists = json_decode(file_get_contents("data/playlists.json"), true);

        foreach($playlists as $id => $data) {
            addPlaylist($id, $data['type']);
        }