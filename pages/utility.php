<?php

if (!defined('AOWOW_REVISION'))
    die('illegal access');


// menuId  8: Utilities g_initPath()
//  tabId  1: Tools     g_initHeader()
class UtilityPage extends GenericPage
{
    protected $tpl           = 'list-page-generic';
    protected $path          = [1, 8];
    protected $tabId         = 1;
    protected $mode          = CACHE_TYPE_NONE;
    protected $validPages    = array(
        null,                     null,                    'latest-comments',       'latest-screenshots',  'random',
        'unrated-comments', 11 => 'latest-videos',   12 => 'most-comments',   13 => 'missing-screenshots'
    );

    protected $_get          = ['rss' => ['filter' => FILTER_CALLBACK, 'options' => 'GenericPage::checkEmptySet']];

    private $page            = '';
    private $rss             = false;
    private $feedData        = [];

    public function __construct($pageCall, $pageParam)
    {
        $this->getCategoryFromUrl($pageParam);

        parent::__construct($pageCall, $pageParam);

        $this->page = $pageCall;
        $this->rss  = $this->_get['rss'];

        if ($this->page != 'random')
            $this->name = Lang::main('utilities', array_search($pageCall, $this->validPages));

        if ($this->page == 'most-comments')
        {
            if ($this->category && in_array($this->category[0], [7, 30]))
                $this->name .= Lang::main('colon') . sprintf(Lang::main('mostComments', 1), $this->category[0]);
            else
                $this->name .= Lang::main('colon') . Lang::main('mostComments', 0);
        }

        $this->lvTabs = [];
    }

    public function display(string $override = '') : void
    {
        if ($this->rss)                                     // this should not be cached
        {
            header(MIME_TYPE_RSS);
            die($this->generateRSS());
        }
        else
            parent::display($override);
    }

    protected function generateContent()
    {
        /****************/
        /* Main Content */
        /****************/

        if (in_array(array_search($this->page, $this->validPages), [2, 3, 11, 12]))
            $this->h1Links = '<small><a href="?'.$this->page.($this->category ? '='.$this->category[0] : null).'&rss" class="icon-rss">'.Lang::main('subscribe').'</a></small>';

        switch ($this->page)
        {
            case 'random':
                $type   = array_rand(Type::getClassesFor(Type::FLAG_RANDOM_SEARCHABLE));
                $typeId = (Type::newList($type, null))?->getRandomId();

                header('Location: ?'.Type::getFileString($type).'='.$typeId, true, 302);
                die();
            case 'latest-comments':                         // rss
                $data = CommunityContent::getCommentPreviews(dateFmt: false);

                if ($this->rss)
                {
                    foreach ($data as $d)
                    {
                        // todo (low): preview should be html-formated
                        $this->feedData[] = array(
                            'title'       => [true,  [], Lang::typeName($d['type']).Lang::main('colon').htmlentities($d['subject'])],
                            'link'        => [false, [], HOST_URL.'/?go-to-comment&amp;id='.$d['id']],
                            'description' => [true,  [], htmlentities($d['preview'])."<br /><br />".Lang::main('byUser', [$d['user'], '']) . Util::formatTimeDiff($d['date'], true)],
                            'pubDate'     => [false, [], date(DATE_RSS, $d['date'])],
                            'guid'        => [false, [], HOST_URL.'/?go-to-comment&amp;id='.$d['id']]
                         // 'domain'      => [false, [], null]
                        );
                    }
                }
                else
                    $this->lvTabs[] = ['commentpreview', ['data' => array_values($data)]];

                break;
            case 'latest-screenshots':                      // rss
                $data = CommunityContent::getScreenshots(dateFmt: false);

                if ($this->rss)
                {
                    foreach ($data as $d)
                    {
                        $desc = '<a href="'.HOST_URL.'/?'.Type::getFileString($d['type']).'='.$d['typeId'].'#screenshots:id='.$d['id'].'"><img src="'.STATIC_URL.'/uploads/screenshots/thumb/'.$d['id'].'.jpg" alt="" /></a>';
                        if ($d['caption'])
                            $desc .= '<br />'.$d['caption'];
                        $desc .= "<br /><br />".Lang::main('byUser', [$d['user'], '']) . Util::formatTimeDiff($d['date'], true);

                        // enclosure/length => filesize('static/uploads/screenshots/thumb/'.$d['id'].'.jpg') .. always set to this placeholder value though
                        $this->feedData[] = array(
                            'title'       => [true,  [], Lang::typeName($d['type']).Lang::main('colon').htmlentities($d['subject'])],
                            'link'        => [false, [], HOST_URL.'/?'.Type::getFileString($d['type']).'='.$d['typeId'].'#screenshots:id='.$d['id']],
                            'description' => [true,  [], $desc],
                            'pubDate'     => [false, [], date(DATE_RSS, $d['date'])],
                            'enclosure'   => [false, ['url' => STATIC_URL.'/uploads/screenshots/thumb/'.$d['id'].'.jpg', 'length' => 12345, 'type' => 'image/jpeg'], null],
                            'guid'        => [false, [], HOST_URL.'/?'.Type::getFileString($d['type']).'='.$d['typeId'].'#screenshots:id='.$d['id']],
                         // 'domain'      => [false, [], live|ptr]
                        );
                    }
                }
                else
                    $this->lvTabs[] = ['screenshot', ['data' => array_values($data)]];

                break;
            case 'latest-videos':                           // rss
                $data = CommunityContent::getVideos(dateFmt: false);

                if ($this->rss)
                {
                    foreach ($data as $d)
                    {
                        $desc = '<a href="'.HOST_URL.'/?'.Type::getFileString($d['type']).'='.$d['typeId'].'#videos:id='.$d['id'].'"><img src="//i3.ytimg.com/vi/'.$d['videoId'].'/default.jpg" alt="" /></a>';
                        if ($d['caption'])
                            $desc .= '<br />'.$d['caption'];
                            $desc .= "<br /><br />".Lang::main('byUser', [$d['user'], '']) . Util::formatTimeDiff($d['date'], true);

                        // is enclosure/length .. is this even relevant..?
                        $this->feedData[] = array(
                            'title'       => [true,  [], Lang::typeName($d['type']).Lang::main('colon').htmlentities($d['subject'])],
                            'link'        => [false, [], HOST_URL.'/?'.Type::getFileString($d['type']).'='.$d['typeId'].'#videos:id='.$d['id']],
                            'description' => [true,  [], $desc],
                            'pubDate'     => [false, [], date(DATE_RSS, $d['date'])],
                            'enclosure'   => [false, ['url' => '//i3.ytimg.com/vi/'.$d['videoId'].'/default.jpg', 'length' => 12345, 'type' => 'image/jpeg'], null],
                            'guid'        => [false, [], HOST_URL.'/?'.Type::getFileString($d['type']).'='.$d['typeId'].'#videos:id='.$d['id']],
                         // 'domain'      => [false, [], live|ptr]
                        );
                    }
                }
                else
                    $this->lvTabs[] = ['video', ['data' => array_values($data)]];

                break;
            case 'unrated-comments':

                // EXPLAIN SELECT ac.* FROM aowow_comments ac LEFT JOIN aowow_comments_rates acr ON acr.commentId = ac.id AND acr.userId <> 0 WHERE acr.commentId IS NULL;
                if ($_ = CommunityContent::getCommentPreviews(['user' => User::$id, 'replies' => false], $nFound))
                {
                    $tabData = array(
                        'data'           => $_,
                        'onBeforeCreate' => '$Listview.funcBox.beforeUserComments',
                        '_totalCount'    => $nFound
                    );

                    if ($nFound > CFG_SQL_LIMIT_DEFAULT)
                    {
                        $tabData['name'] = '$LANG.tab_latestcomments';
                        $tabData['note'] = '$$WH.sprintf(LANG.lvnote_usercomments, '.$nFound.')';
                    }

                    $this->lvTabs[] = ['commentpreview', $tabData];
                }


                $this->lvTabs[] = ['commentpreview', ['data' => []]];
                break;
            case 'missing-screenshots':
                // limit to 200 entries each (it generates faster, consumes less memory and should be enough options)
                $cnd = [[['cuFlags', CUSTOM_HAS_SCREENSHOT, '&'], 0], 200];
                if (!User::isInGroup(U_GROUP_EMPLOYEE))
                    $cnd[] = [['cuFlags', CUSTOM_EXCLUDE_FOR_LISTVIEW, '&'], 0];


                foreach (Type::getClassesFor(Type::FLAG_NONE, 'contribute', CONTRIBUTE_SS) as $type => $classStr)
                {
                    $typeObj = new $classStr($cnd);
                    if (!$typeObj->error)
                    {
                        $this->extendGlobalData($typeObj->getJSGlobals(GLOBALINFO_ANY));
                        $this->lvTabs[] = [$typeObj::$brickFile, ['data' => array_values($typeObj->getListviewData())]];
                    }
                }
                break;
            case 'most-comments':                           // rss
                if ($this->category && !in_array($this->category[0], [1, 7, 30]))
                    header('Location: ?most-comments=1'.($this->rss ? '&rss' : null), true, 302);

                $tabBase = array(
                    'extraCols' => ["\$Listview.funcBox.createSimpleCol('ncomments', 'tab_comments', '10%', 'ncomments')"],
                    'sort'      => ['-ncomments']
                );

                foreach (Type::getClassesFor() as $type => $classStr)
                {
                    $comments = DB::Aowow()->selectCol('
                        SELECT   `typeId` AS ARRAY_KEY, count(1) FROM ?_comments
                        WHERE    `replyTo` = 0 AND (`flags` & ?d) = 0 AND `type`= ?d AND `date` > (UNIX_TIMESTAMP() - ?d)
                        GROUP BY `type`, `typeId`
                        LIMIT    100',
                        CC_FLAG_DELETED,
                        $type,
                        (isset($this->category[0]) ? $this->category[0] : 1) * DAY
                    );
                    if (!$comments)
                        continue;

                    $typeClass = new $classStr(array(['id', array_keys($comments)]));
                    if (!$typeClass->error)
                    {
                        $data = $typeClass->getListviewData();

                        if ($this->rss)
                        {
                            foreach ($data as $typeId => &$d)
                            {
                                $this->feedData[] = array(
                                    'title'       => [true,  [], htmlentities(Type::getFileString($type) == 'item' ? mb_substr($d['name'], 1) : $d['name'])],
                                    'type'        => [false, [], Type::getFileString($type)],
                                    'link'        => [false, [], HOST_URL.'/?'.Type::getFileString($type).'='.$d['id']],
                                    'ncomments'   => [false, [], $comments[$typeId]]
                                );
                            }
                        }
                        else
                        {
                            foreach ($data as $typeId => &$d)
                                $d['ncomments'] = $comments[$typeId];

                            $this->extendGlobalData($typeClass->getJSGlobals(GLOBALINFO_ANY));
                            $this->lvTabs[] = [$typeClass::$brickFile, array_merge($tabBase, ['data' => array_values($data)])];
                        }
                    }
                }

                break;
        }

        // found nothing => set empty content
        // tpl: commentpreview - anything, doesn't matter what
        if (!$this->lvTabs && !$this->rss)
            $this->lvTabs[] = ['commentpreview', ['data' => []]];
    }

    protected function generateRSS()
    {
        $this->generateContent();

        $root = new SimpleXML('<rss />');
        $root->addAttribute('version', '2.0');

        $channel = $root->addChild('channel');

        $channel->addChild('title',         CFG_NAME_SHORT.' - '.$this->name);
        $channel->addChild('link',          HOST_URL.'/?'.$this->page . ($this->category ? '='.$this->category[0] : null));
        $channel->addChild('description',   CFG_NAME);
        $channel->addChild('language',      implode('-', str_split(User::$localeString, 2)));
        $channel->addChild('ttl',           CFG_TTL_RSS);
        $channel->addChild('lastBuildDate', date(DATE_RSS));

        foreach ($this->feedData as $row)
        {
            $item = $channel->addChild('item');

            foreach ($row as $key => [$isCData, $attrib, $text])
            {
                if ($isCData && $text)
                    $child = $item->addChild($key)->addCData($text);
                else
                    $child = $item->addChild($key, $text);

                foreach ($attrib as $k => $v)
                    $child->addAttribute($k, $v);
            }
        }

        return $root->asXML();
    }

    protected function generateTitle()
    {
        if ($this->page == 'most-comments')
        {
            if ($this->category && in_array($this->category[0], [7, 30]))
                array_unshift($this->title, sprintf(Lang::main('mostComments', 1), $this->category[0]));
            else
                array_unshift($this->title, Lang::main('mostComments', 0));
        }

        array_unshift($this->title, $this->name);
    }

    protected function generatePath()
    {
        $this->path[] = array_search($this->page, $this->validPages);

        if ($this->page == 'most-comments')
        {
            if ($this->category && in_array($this->category[0], [7, 30]))
                $this->path[] = $this->category[0];
            else
                $this->path[] = 1;
        }
    }
}

?>
