<?php
$month = isset($argv[1]) ? (int)$argv[1] : date('n');
$year = isset($argv[2]) ? (int)$argv[2] : date('Y');
$config = parse_ini_file($_SERVER['HOME'] . '/.odiostatsrc');
$finished = FALSE;
$url = 'https://graph.facebook.com/227624113988088/feed?access_token=' . $config['access_token'] . '&limit=500';

$people = array();
$posts_per_people = array();
$top_liked_post = null;
$top_commented_post = null;
do {
	$response = file_get_contents($url);
	$info = json_decode($response);

	foreach ($info->data as $post) {
		$created = strtotime($post->created_time);
		$updated = strtotime($post->updated_time);
		if ((int)date('n', $updated) < $month || date('Y', $updated) < $year) {
			$finished = TRUE;
			break;
		}

		if ((int)date('n', $created) != $month || date('Y', $created) != $year) {
			continue;
		}

		$people[$post->from->id] = $post->from;
		@$posts_per_people[$post->from->id]++;
		if (@$top_liked_post == NULL || @$top_liked_post->likes->count < @$post->likes->count) $top_liked_post = $post;
		if ($top_commented_post == NULL || $top_commented_post->comments->count < $post->comments->count) $top_commented_post = $post;
	}
	// TODO: pagination seems to be broken on facebook!
	//if (empty($info->paging)) break;
	//$url = $info->paging->previous;
	//if (empty($url)) break;
	break;
} while (!$finished);
echo "Máximo odiador: " , $people[array_search(max($posts_per_people), $posts_per_people)]->name , " con " , max($posts_per_people) , " mensajes\n";
echo "Odio más compartido: Con " , $top_liked_post->likes->count , " votos, \"" , $top_liked_post->message , "\" " , $top_liked_post->actions[0]->link , "\n";
echo "Odio más discutido: Con " , $top_commented_post->comments->count , " comentarios, \"" , $top_commented_post->message , "\" " , $top_commented_post->actions[0]->link , "\n";
