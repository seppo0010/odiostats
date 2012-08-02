<?php

function getMessageString($post) {
	$result = "";
	$type = $post->type;
	if ($type == 'status') {
		$result = $post->message;
	} elseif ($type == 'link') {
		$result = $post->link;
	}
	return $result;
}

date_default_timezone_set('America/Buenos_Aires');
$month = isset($argv[1]) ? (int)$argv[1] : date('n');
$year = isset($argv[2]) ? (int)$argv[2] : date('Y');
if (!is_file($_SERVER['HOME'] . '/.odiostatsrc')) {
	die("Falta el access_token\n");
}
$config = parse_ini_file($_SERVER['HOME'] . '/.odiostatsrc');
if (empty($config['access_token'])) {
	die("Falta el access_token\n");
}
if (empty($config['group_id'])) {
	die("Falta el group_id. Para 'Odio a la gente que...' usar 227624113988088\n");
}
$finished = FALSE;
$url = 'https://graph.facebook.com/' . $config['group_id'] . '/feed?access_token=' . $config['access_token'] . '&limit=1000';

$people = array();
$posts_per_people = array();
$autocomments_per_people = array();
$comments_per_people = array();
$comments_received_per_people = array();
$autolikes_per_people = array();
$likes_per_people = array();
$likes_received_per_people = array();
$top_liked_post = null;
$bottom_liked_post = null;
$top_commented_post = null;
$bottom_commented_post = null;
do {
	($response = file_get_contents($url)) || die("Error en la respuesta de Facebook.\n");
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

		$from = $post->from;
		$from_id = $from->id;

		$comments = @$post->comments->count + 0;
		if ($comments > 0) {
			foreach ($post->comments->data as $comment) {
				$commentator = $comment->from;
				$commentator_id = $commentator->id;
				if ($from_id == $commentator_id) {
					--$comments;
					@$autocomments_per_people[$commentator_id]++;
				}
				$people[$commentator_id] = $commentator;
				@$comments_per_people[$commentator_id]++;
			}
			// Subtracting the comments made by the same person who did the post
			$post->comments->count = $comments;
		}

		$likes = @$post->likes->count + 0;
		if ($likes > 0) {
			foreach ($post->likes->data as $like) {
				$liker_id = $like->id;
				if ($from_id == $liker_id) {
					--$likes;
					@$autolikes_per_people[$liker_id]++;
				}
				$people[$liker_id] = $like;
				@$likes_per_people[$liker_id]++;
			}
			// Subtracting the auto-likes
			$post->likes->count = $likes;
		}

		$people[$from_id] = $from;
		@$posts_per_people[$from_id]++;
		@$comments_received_per_people[$from_id] += $comments;
		@$likes_received_per_people[$from_id] += $likes;

		if ($top_commented_post == NULL || @$top_commented_post->comments->count < $comments) $top_commented_post = $post;
		if ($bottom_commented_post == NULL || @$bottom_commented_post->comments->count > $comments) $bottom_commented_post = $post;
		if ($top_liked_post == NULL || @$top_liked_post->likes->count < $likes) $top_liked_post = $post;
		if ($bottom_liked_post == NULL || @$bottom_liked_post->likes->count > $likes) $bottom_liked_post = $post;
	}
	// TODO: pagination seems to be broken on facebook!
	//if (empty($info->paging)) break;
	//$url = $info->paging->previous;
	//if (empty($url)) break;
	break;
} while (!$finished);

if (count($people) == 0) die("No hubo posts este mes.\n");

echo "Máximo odiador: " , $people[array_search(max($posts_per_people), $posts_per_people)]->name , " con " , max($posts_per_people) , " mensajes\n";

echo "Tipo más carismático: " , $people[array_search(max($likes_received_per_people), $likes_received_per_people)]->name , " con " , max($likes_received_per_people) , " votos\n";

echo "Tipo más controversial: " , $people[array_search(max($comments_received_per_people), $comments_received_per_people)]->name , " con " , max($comments_received_per_people) , " comentarios recibidos\n";

if (count($comments_per_people) > 0) {
	echo "Máximo opinólogo: " , $people[array_search(max($comments_per_people), $comments_per_people)]->name , " con " , max($comments_per_people) , " comentarios\n";
}

if (count($autolikes_per_people) > 0) {
	echo "Máximo onanista: " , $people[array_search(max($autolikes_per_people), $autolikes_per_people)]->name , " con " , max($autolikes_per_people) , " auto-likes\n";
}

if (count($autocomments_per_people) > 0) {
	echo "Máximo autobombista: " , $people[array_search(max($autocomments_per_people), $autocomments_per_people)]->name , " con " , max($autocomments_per_people) , " comentarios a posts propios\n";
}

$max_ignored = null;
$max_ignored_comments_received = null;
$max_ignored_likes_received = null;
$max_ignored_posts = null;
$max_ignored_ratio = null;
foreach (@$people as $person) {
	$posts = @$posts_per_people[$person->id];
	if ($posts > 0) {
		$comments_received = @$comments_received_per_people[$person->id];
		$likes_received = @$likes_received_per_people[$person->id];
		$ratio = ($comments_received + $likes_received) / $posts;
		if ($max_ignored == null || $max_ignored_ratio > $ratio || ($max_ignored_ratio == $ratio && $max_ignored_posts < $posts)) {
			$max_ignored = $person;
			$max_ignored_comments_received = $comments_received;
			$max_ignored_likes_received = $likes_received;
			$max_ignored_posts = $posts;
			$max_ignored_ratio = $ratio;
		}
	}
}
echo "Máximo ignorado: " , $max_ignored->name , " con " , $max_ignored_posts , " mensajes, " , $max_ignored_comments_received , " comentarios recibidos y " , $max_ignored_likes_received , " votos\n";

echo "Odio más compartido: Con " , (@$top_liked_post->likes->count + 0) , " votos ajenos, \"" , getMessageString($top_liked_post) , "\" " , $top_liked_post->actions[0]->link , "\n";

echo "Odio menos compartido: Con " , (@$bottom_liked_post->likes->count + 0) , " votos ajenos, \"" , getMessageString($bottom_liked_post) , "\" " , $bottom_liked_post->actions[0]->link , "\n";

echo "Odio más discutido: Con " , (@$top_commented_post->comments->count + 0) , " comentarios ajenos, \"" , getMessageString($top_commented_post) , "\" " , $top_commented_post->actions[0]->link , "\n";

echo "Odio menos discutido: Con " , (@$bottom_commented_post->comments->count + 0) , " comentarios ajenos, \"" , getMessageString($bottom_commented_post) , "\" " , $bottom_commented_post->actions[0]->link , "\n";

