<?php
/*
 * Structured data and search-engine metadata.
 *
 * Kept apart from view.php because this is not layout: it is a second,
 * machine-readable description of the same page. Building it here means the
 * schema.org output cannot drift away from the visible content, since both are
 * generated from the same row.
 *
 * WHAT SEARCH ENGINES GET
 *   - One canonical URL per page, and exactly one spelling of that URL.
 *   - JSON-LD as an @graph: the site itself, breadcrumbs, and the page's own
 *     entity (BlogPosting for a post, CollectionPage for a topic).
 *   - Open Graph and Twitter cards for link previews.
 *   - rel=prev/next on paged feeds, and self-referencing canonicals on them --
 *     not noindex. A page-2 marked noindex hides the posts it links to.
 *
 * WHAT THEY DO NOT GET
 *   Search results, the moderation queue, the migration runner, and the edit
 *   page are all noindex. They are either infinite URL spaces or operator
 *   tools, and neither belongs in an index.
 */

/** Identity of the site itself, plus the search box crawlers can surface. */
function jsonld_website(): array {
  return [
    '@type'    => 'WebSite',
    '@id'      => site_url('/') . '#website',
    'url'      => site_url('/'),
    'name'     => 'ExchangeMyIdeas',
    'description' => "A minimalistic blog where anyone can post an idea, reply to others, and search everything that's been shared.",
    'inLanguage'  => 'en',
    'potentialAction' => [
      '@type'       => 'SearchAction',
      'target'      => [
        '@type'       => 'EntryPoint',
        'urlTemplate' => site_url('/search') . '?q={search_term_string}',
      ],
      'query-input' => 'required name=search_term_string',
    ],
  ];
}

/**
 * Breadcrumbs.
 *
 * @param array $trail list of ['name' => string, 'path' => string]
 */
function jsonld_breadcrumbs(array $trail): array {
  $items = [];
  foreach ($trail as $i => $crumb) {
    $items[] = [
      '@type'    => 'ListItem',
      'position' => $i + 1,
      'name'     => $crumb['name'],
      'item'     => site_url($crumb['path']),
    ];
  }
  return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** A post, as an article. */
function jsonld_post(array $post, array $tags, int $replyCount): array {
  $url = site_url(post_path($post['post_id']));
  $published = date('c', strtotime($post['date_posted']));

  $data = [
    '@type'            => 'BlogPosting',
    '@id'              => $url . '#post',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    'url'              => $url,
    'headline'         => mb_substr($post['title'], 0, 110),
    'description'      => post_excerpt($post['content'], 200),
    'articleBody'      => post_excerpt($post['content'], 1200),
    'datePublished'    => $published,
    'dateModified'     => !empty($post['edited_at']) ? date('c', strtotime($post['edited_at'])) : $published,
    'author'           => ['@type' => 'Person', 'name' => $post['author_name']],
    'publisher'        => [
      '@type' => 'Organization',
      'name'  => 'ExchangeMyIdeas',
      'url'   => site_url('/'),
    ],
    'commentCount'     => $replyCount,
    'isAccessibleForFree' => true,
    'inLanguage'       => 'en',
  ];

  if ($tags) {
    $data['keywords'] = implode(', ', array_column($tags, 'label'));
    $data['about'] = array_map(
      fn(array $tag) => ['@type' => 'Thing', 'name' => $tag['label'], 'url' => site_url(tag_path($tag['slug']))],
      $tags
    );
  }

  return $data;
}

/** A topic page, as a collection of posts. */
function jsonld_collection(string $name, string $description, string $path, array $posts): array {
  $items = [];
  foreach (array_values($posts) as $i => $post) {
    $items[] = [
      '@type'    => 'ListItem',
      'position' => $i + 1,
      'url'      => site_url(post_path($post['post_id'])),
      'name'     => $post['title'],
    ];
  }

  return [
    '@type'       => 'CollectionPage',
    '@id'         => site_url($path) . '#collection',
    'url'         => site_url($path),
    'name'        => $name,
    'description' => $description,
    'inLanguage'  => 'en',
    'isPartOf'    => ['@id' => site_url('/') . '#website'],
    'mainEntity'  => ['@type' => 'ItemList', 'itemListElement' => $items],
  ];
}

/**
 * Wrap the page's JSON-LD objects into a single @graph.
 *
 * One script tag holding a graph is easier for crawlers to relate than several
 * disconnected blocks, and it lets entities reference each other by @id.
 */
function jsonld_graph(array $nodes): string {
  return json_encode(
    ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($nodes))],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
  ) ?: '';
}
