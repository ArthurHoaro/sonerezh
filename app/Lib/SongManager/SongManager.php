<?php

App::uses('Folder', 'Utility');
App::import('Vendor', 'Getid3/getid3');

class SongManager {

    protected $song;

    function __construct($song) {
        $this->song = new File($song);
    }

    function parseMetadata() {
        $getID3 = new getID3();
        $file_infos = $getID3->analyze(($this->song->path));
        getid3_lib::CopyTagsToComments($file_infos);

        // Can be useful to add more debug in the future
        $result = array(
            'status' => 'OK',   // 'OK', 'WARN' or 'ERR'
            'message' => '',    // Debug message
            'data' => array()   // The data ($metadata array below)
        );

        if (!isset($file_infos['comments']) || empty($file_infos['comments'])) {
            $result['status'] = 'WARN';
            $result['message'] = 'Metadata are unreadable or empty. Trying to import anyway...';
        }

        $metadata = array();

        // Song title
        if (!empty($file_infos['comments']['title'])) {
            $metadata['title'] = end($file_infos['comments']['title']);
        } elseif (!empty($file_infos['filename'])) {
            $metadata['title'] = $file_infos['filename'];
        } else {
            $metadata['title'] = $this->song->name();
        }

        // Song artist
        if (!empty($file_infos['comments']['artist'])) {
            $metadata['artist'] = end($file_infos['comments']['artist']);
        } else {
            $metadata['artist'] = '';
        }

        // Song band
        if (!empty($file_infos['comments']['band'])) {              // MP3 Tag
            $metadata['band'] = end($file_infos['comments']['band']);
        } elseif (!empty($file_infos['comments']['ensemble'])) {    // OGG Tag
            $metadata['band'] = end($file_infos['comments']['ensemble']);
        } elseif (!empty($file_infos['comments']['albumartist'])) { // OGG/FLAC Tag
            $metadata['band'] = end($file_infos['comments']['albumartist']);
        } elseif (!empty($file_infos['comments']['album artist'])) {// OGG/FLAC Tag
            $metadata['band'] = end($file_infos['comments']['album artist']);
        } else {
            $metadata['band'] = $metadata['artist'] != '' ? $metadata['artist'] : 'Unknown Band';
        }

        // Song album
        if (!empty($file_infos['comments']['album'])) {
            $metadata['album'] = end($file_infos['comments']['album']);
        } else {
            $metadata['album'] = 'Unknown album';
        }

        // Song track number
        if (!empty($file_infos['comments']['track'])) {              // MP3 Tag
            $metadata['track_number'] = (string)end($file_infos['comments']['track']);
        } elseif (!empty($file_infos['comments']['track_number'])) { // MP3 Tag
            // Some tags look like '1/10'
            $track_number = explode('/', (string)end($file_infos['comments']['track_number']));
            $metadata['track_number'] = intval($track_number[0]);
        } elseif(!empty($file_infos['comments']['tracknumber'])){   // OGG Tag
            $metadata['track_number'] = end($file_infos['comments']['tracknumber']);
        }
        if (!empty($metadata['track_number']) && !is_int($metadata['track_number'])) {
            $metadata['track_number'] = null;
        }

        // Song playtime
        if (!empty($file_infos['playtime_string'])) {
            $metadata['playtime'] = $file_infos['playtime_string'];
        }

        // Song year
        if (!empty($file_infos['comments']['year']) && is_int($file_infos['comments']['year'])) {
            $metadata['year'] = end($file_infos['comments']['year']);
        }

        // Song set
        if (!empty($file_infos['comments']['part_of_a_set'])) {     // MP3 Tag
            $metadata['disc'] = end($file_infos['comments']['part_of_a_set']);
        } elseif (!empty($file_infos['comments']['discnumber'])) {  // OGG Tag
            $metadata['disc'] = end($file_infos['comments']['discnumber']);
        }

        // Song genre
        if (!empty($file_infos['comments']['genre'])) {
            $metadata['genre'] = end($file_infos['comments']['genre']);
        }

        // Song cover
        if (!empty($file_infos['comments']['picture'])) {
            $array_length = count($file_infos['comments']['picture']);
            if (!empty($file_infos['comments']['picture'][$array_length - 1]['image_mime'])) {
                $mime_type = preg_split('/\//', $file_infos['comments']['picture'][$array_length - 1]['image_mime']);
                $cover_extension = $mime_type[1];
            } else {
                $cover_extension = 'jpg';
            }

            $cover_name = md5($metadata['artist'].$metadata['album']) . '.' . $cover_extension;
            $cover_path = new File(IMAGES.THUMBNAILS_DIR.DS.$cover_name);

            // IF the cover already exists
            // OR the cover doesn't exist AND has been successfully written
            if (
                $this->thumbnailExists($cover_name)
                || (
                    !$this->thumbnailExists($cover_name)
                    && $cover_path->write($file_infos['comments']['picture'][$array_length - 1]['data'])
                )
            ) {
                $metadata['cover'] = $cover_name;
            }

        } else {
            $cover_pattern = '^(folder|cover|front.*|albumart_.*_large)\.(jpg|jpeg|png)$';
            $covers = $this->song->Folder->find($cover_pattern);

            if (!empty($covers)) {
                $cover_source_path = $this->song->Folder->addPathElement(
                    $this->song->Folder->path,
                    $covers[0]
                );
                $cover_source = new File($cover_source_path);
                $cover_info = $cover_source->info();
                $cover_extension = $cover_info['extension'];
                $cover_name = md5($metadata['artist'].$metadata['album']) . '.' . $cover_extension;

                // IF the cover already exists
                // OR the cover doesn't exist AND has been successfully copied
                if (
                    $this->thumbnailExists($cover_name)
                    || (
                        !$this->thumbnailExists($cover_name)
                        && $cover_source->copy(IMAGES.THUMBNAILS_DIR.DS.$cover_name)
                    )
                ) {
                    $metadata['cover'] = $cover_name;
                }
            }
        }

        $metadata['source_path'] = $this->song->path;
        $result['data'] = $metadata;
        return $result;
    }

    public function thumbnailExists($cover_name) {
        $exists = true;

        if (!file_exists(IMAGES.THUMBNAILS_DIR.DS.$cover_name)) {

            if (!file_exists(IMAGES.THUMBNAILS_DIR)) {
                new Folder(IMAGES.THUMBNAILS_DIR, true, 0755);
            }

            $exists = false;
        }

        return $exists;
    }
}
