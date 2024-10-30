<?php 

class LastFmParser
{
    function LastFMParser($xmlAsAssoc,$panel="tmrt")
    { // pass in the root node and return a simple assoc array with artist, album, track, artwork_url
        $rootNode = array_slice($xmlAsAssoc[0]['value'][0]['value'],0,10); // only show ten results at a time
        foreach($rootNode as $trackID=>$track){
            foreach($track['value'] as $trackData){
                switch($trackData['tag']){
                    case "artist":
                        if(is_array($trackData['value'])){
                            $data[$trackID]['artist']=$trackData['value'][0]['value'];
                        } else {
                            $data[$trackID]['artist']=$trackData['value'];
                        }
                    break;
                    case "name":
                        $data[$trackID]['title'] = $trackData['value'];
                    break;
                    case "album":
                        $data[$trackID]['album'] = $trackData['value'];
                    break;
                    case "image":
                        if($trackData['attributes']['size'] == "small") $data[$trackID]['image_url'] = $trackData['value'];
                        if($trackData['attributes']['size'] == "large") $data[$trackID]['large_image_url'] = $trackData['value'];
                    break;
                }
                
                $data[$trackID]['td_url'] = create_td_link($data[$trackID]['artist'],$data[$trackID]['album'],$data[$trackID]['title']);
            }
        }
        
        $this->data = $data;
    }
}

function xml2assoc($xml) {
    $tree = null;
    while($xml->read())
        switch ($xml->nodeType) {
            case XMLReader::END_ELEMENT: return $tree;
            case XMLReader::ELEMENT:
                $node = array('tag' => $xml->name, 'value' => $xml->isEmptyElement ? '' : xml2assoc($xml));
                if($xml->hasAttributes)
                    while($xml->moveToNextAttribute())
                        $node['attributes'][$xml->name] = $xml->value;
                $tree[] = $node;
            break;
            case XMLReader::TEXT:
            case XMLReader::CDATA:
                $tree .= $xml->value;
        }
    return $tree;
}

?>