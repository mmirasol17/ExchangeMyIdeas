<?php
/*
 * Term and pattern lists for the moderation engine in moderation.php.
 *
 * This file exists so the unpleasant vocabulary a content filter has to know
 * lives in exactly one place. Nothing here is displayed to anyone -- the lists
 * are only ever matched against submitted text.
 *
 * HOW MATCHING WORKS
 *   Plain entries are lowercase [a-z0-9 ] only. moderation.php turns each one
 *   into a whole-word pattern that tolerates stretched letters, so "fuck" also
 *   catches "fuuuuck" and "fuckkkk", while word boundaries keep it from firing
 *   inside innocent words ("shiitake" is not "shit", "assassin" is not "ass").
 *   Obfuscation with digits, symbols, or spacing ("f.u.c.k", "sh1t") is handled
 *   by normalisation before matching, not by the lists.
 *
 *   *_patterns entries are raw regex bodies (no delimiters, no anchors) for the
 *   cases a word list cannot express.
 *
 * TIERS
 *   hate_severe / threat_patterns  Unambiguous. One hit blocks the submission.
 *   hate_mild                      Genuinely ambiguous in ordinary English
 *                                  ("dyke" is also a levee, "fag" is also a
 *                                  cigarette in British usage). These flag for
 *                                  human review rather than blocking, which is
 *                                  what the review queue is for.
 *   sexual / profanity / spam      Scored; enough of them together will block.
 *
 * Editing these lists is expected. Add a term, and the engine picks it up on
 * the next request -- there is nothing to rebuild.
 */

return [

  // Unambiguous slurs. A single hit blocks the submission outright.
  'hate_severe' => [
    'chink', 'gook', 'spic', 'wetback', 'beaner', 'kike', 'heeb',
    'towelhead', 'raghead', 'sandnigger', 'jigaboo', 'porchmonkey',
    'zipperhead', 'darkie', 'coon', 'tarbaby',
    'faggot', 'faggots', 'fagot', 'tranny', 'shemale', 'ladyboy',
    'retard', 'retards', 'retarded', 'mongoloid',
    'goatfucker', 'motherfucker',
  ],

  // Regex counterparts for slurs a plain word list cannot express safely.
  'hate_severe_patterns' => [
    // Requires the doubled g, so the country "Niger" and "Nigerian" are unaffected.
    'n+i+g{2,}(a+|e+r+|u+h+|a+h+)',
    // "n word" spelled out with separators already stripped by normalisation.
    'p+o+r+c+h+m+o+n+k+e+y+',
  ],

  // Ambiguous in ordinary use. These flag for review instead of blocking.
  'hate_mild' => [
    'dyke', 'fag', 'fags', 'queer', 'homo', 'gypsy', 'gyppo',
    'cripple', 'spastic', 'spaz', 'paki', 'honky', 'redneck',
    'savage', 'primitive',
  ],

  // Explicit sexual content. Weighted high enough that two hits block.
  'sexual' => [
    'porn', 'porno', 'pornhub', 'xxx', 'hentai', 'bukkake', 'creampie',
    'cumshot', 'blowjob', 'handjob', 'rimjob', 'deepthroat', 'gangbang',
    'dildo', 'buttplug', 'fleshlight', 'camgirl', 'escort', 'escorts',
    'milf', 'nudes', 'sexcam', 'onlyfans', 'jizz', 'cunnilingus',
    'masturbate', 'masturbating', 'anal sex', 'oral sex',
  ],

  // Strong profanity. Scored, not fatal on its own -- people swear.
  'profanity' => [
    'fuck', 'fucks', 'fucking', 'fucked', 'fucker', 'fuckers', 'fuckin',
    'bullshit', 'horseshit',
    'bitch', 'bitches', 'bitching', 'bastard', 'bastards',
    'cunt', 'cunts', 'twat', 'wanker', 'wank', 'tosser',
    'asshole', 'assholes', 'arsehole', 'dumbass', 'jackass',
    'dickhead', 'prick', 'cock', 'bollocks',
    'slut', 'sluts', 'whore', 'whores', 'skank',
    'pussy', 'pussies', 'douchebag',
  ],

  // Mild profanity. Barely moves the needle on its own -- a post that says a
  // deploy was "crap" is not a moderation problem. Only a pile-up of these
  // reaches the review threshold.
  'profanity_mild' => [
    'shit', 'shits', 'shitty', 'shitting',
    'damn', 'goddamn', 'goddammit', 'damnit', 'bloody hell',
    'piss', 'pissed', 'pissing', 'crap', 'crappy',
    'ass', 'arse', 'smartass', 'dick', 'knob', 'douche', 'hoe',
    'bugger', 'sod off', 'screw you',
  ],

  // Credible threats and encouragement of self-harm. One hit blocks.
  'threat_patterns' => [
    '(i|we)\s+(a?m\s+|will\s+|gonna\s+|going\s+to\s+)+[a-z ]{0,20}(kill|murder|shoot|stab|rape|behead|strangle|beat)\s+(you|u|him|her|them|yall)',
    '(kill|hang|shoot|off)\s+(yourself|urself|ur\s*self|thyself)',
    '\bkys\b',
    'you\s+(should|deserve\s+to|need\s+to|oughta|ought\s+to)\s+(die|be\s+killed|be\s+shot|be\s+raped)',
    '(im|i\s+am)\s+(gonna|going\s+to)\s+(find|hunt|come\s+for)\s+(you|u)\s+',
    '(bomb|shoot\s+up|burn\s+down)\s+(the|your|his|her|their)\s+[a-z]+',
    'death\s+to\s+(all\s+)?[a-z]+s',
  ],

  // Directed harassment. Milder than a threat, but worth a human look.
  'harassment_patterns' => [
    'you\s+(are|r|re)\s+(a\s+|an\s+|such\s+a\s+)?(worthless|pathetic|disgusting|subhuman|vermin|trash|garbage)',
    '(nobody|no\s+one)\s+(likes|wants|loves)\s+you',
    'go\s+back\s+to\s+(your\s+)?(country|shithole)',
  ],

  // Classic comment-spam vocabulary.
  'spam' => [
    'viagra', 'cialis', 'levitra', 'tramadol', 'oxycontin', 'phentermine',
    'casino', 'roulette', 'slots online', 'sportsbook', 'betting site',
    'payday loan', 'refinance', 'debt relief',
    'replica watches', 'louis vuitton outlet', 'cheap jerseys',
    'buy followers', 'buy backlinks', 'cheap seo', 'guest post service',
    'work from home', 'be your own boss', 'financial freedom guaranteed',
  ],

  // Spam shapes rather than spam words.
  'spam_patterns' => [
    '(make|earn)\s+(\$\s?)?\d[\d,]*\s*(\+)?\s*(per|a|each)\s+(day|week|hour|month)',
    '(bitcoin|btc|ethereum|crypto|forex|binary\s+options?)[a-z\s,]{0,40}(profit|invest|double|guaranteed|returns?|signals?)',
    '(guaranteed|100%)\s+(profit|returns?|income|winner)',
    '(whatsapp|telegram|signal)\s*(me|us|:|at)?\s*\+?\d[\d\s\-()]{7,}',
    'click\s+(here|this\s+link)\s+(now|to\s+claim|for\s+free)',
    '(limited\s+time|act\s+now|hurry)[a-z\s,!]{0,20}(offer|deal|only)',
    '(dm|inbox|message)\s+me\s+(for|to\s+get)\s+[a-z ]{0,20}(money|crypto|bitcoin|deal|price)',
  ],

  // Whole words removed before matching, so they can never trip a rule.
  // Every entry here is an ordinary word that overlaps a listed term.
  'allow' => [
    'niger', 'nigeria', 'nigerian', 'nigerien',
    'scunthorpe', 'penistone', 'lightwater', 'clitheroe',
    'shiitake', 'assassin', 'assassinate', 'assassination',
    'analysis', 'analyst', 'analytics', 'analyse', 'analyze', 'analog',
    'bass', 'bassist', 'class', 'classes', 'glass', 'grass', 'pass', 'mass',
    'cocktail', 'cockpit', 'cockroach', 'peacock', 'hancock', 'babcock',
    'therapist', 'therapists', 'grape', 'grapes', 'scrape', 'drape',
    'dickens', 'dickinson', 'sussex', 'essex', 'middlesex',
    'cummings', 'succumb', 'circumstance', 'document', 'documents',
    'hoedown', 'shoe', 'shoes', 'phoenix',
    'crappie', 'scrapping', 'skyscraper',
    'homogeneous', 'homogenous', 'homograph', 'homonym', 'homepage',
  ],
];
