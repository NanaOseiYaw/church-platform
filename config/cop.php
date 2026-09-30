<?php

/**
 * The Church of Pentecost — denomination-wide content.
 *
 * Everything in this file is official Church of Pentecost material that is
 * identical across every national and local website (Ghana HQ, UK, USA, Canada,
 * Netherlands …). It is deliberately NOT stored per-church in the database,
 * because an individual assembly does not author or vary it.
 *
 * Assembly-specific content — local leadership, local history, service times,
 * contact details — belongs in the church record / Settings, not here.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Global identity
    |--------------------------------------------------------------------------
    */
    'founded_year'      => 1937,
    'founder'           => 'Pastor James McKeown',
    'founder_lifespan'  => '1900–1989',
    'countries'         => 200,
    'global_membership' => '4.8 million',

    /*
    |--------------------------------------------------------------------------
    | The Tenets of the Church
    |--------------------------------------------------------------------------
    | The eleven tenets are the denomination's formal statement of faith.
    */
    'tenets' => [
        [
            'title' => 'The Bible',
            'body'  => 'We believe in the divine inspiration and authority of the Holy Scriptures; that the Bible is infallible in its declaration, final in its authority, comprehensive and all-sufficient in its provisions.',
            'refs'  => '2 Timothy 3:16–17; 2 Peter 1:20–21',
        ],
        [
            'title' => 'One True God',
            'body'  => 'We believe in the existence of the One True God, Elohim, maker of the whole universe; indefinable but revealed as Triune God — the Father, the Son and the Holy Spirit; one in nature, essence and attributes: omnipotent, omnipresent and omniscient.',
            'refs'  => 'Genesis 1:1, 1:26; Matthew 3:16–17, 28:19; 2 Corinthians 13:14; Psalm 139:7–12',
        ],
        [
            'title' => 'The Depraved Nature of Humanity',
            'body'  => 'We believe that all have sinned and come short of the glory of God, and are subject to eternal punishment, and so stand in need of repentance and regeneration.',
            'refs'  => 'Genesis 3:1–19; Isaiah 53:6; Romans 3:23, 6:23; Acts 2:38; John 3:3, 5; Titus 3:5',
        ],
        [
            'title' => 'The Saviour',
            'body'  => 'We believe humanity\'s need of a Saviour has been met in the person of Jesus Christ — in His deity, virgin birth, atoning death, resurrection and ascension, His abiding intercession, and His second coming to judge the living and the dead.',
            'refs'  => 'Matthew 1:21; John 1:1; Romans 3:25; 1 Corinthians 15:3–4; Acts 1:9–11; Hebrews 7:25',
        ],
        [
            'title' => 'Repentance, Justification & Sanctification',
            'body'  => 'We believe all people must repent and confess their sins before God, and believe in the vicarious death of Jesus Christ before they can be justified before God. We believe in the sanctification of the believer through the working of the Holy Spirit, and in God\'s gift of eternal life to the believer.',
            'refs'  => 'Luke 15:7; Acts 2:38, 3:19; Romans 4:25, 5:1; 1 Corinthians 1:30, 6:11; 1 John 5:11–13',
        ],
        [
            'title' => 'The Ordinances of Baptism and the Lord\'s Supper',
            'body'  => 'We believe in the ordinance of baptism by immersion as the testimony of a convert who has attained a responsible age of thirteen years. Infants and children are not baptised but are dedicated to the Lord. We believe in the ordinance of the Lord\'s Supper, to be partaken by all members in full fellowship.',
            'refs'  => 'Matthew 3:16, 28:19; Mark 16:16; Acts 2:38; Luke 22:19–20; 1 Corinthians 11:23–33',
        ],
        [
            'title' => 'Baptism, Gifts & Fruit of the Holy Spirit',
            'body'  => 'We believe in the baptism of the Holy Spirit for all believers, with the initial evidence of speaking in tongues, and in the operation of the gifts and the fruit of the Holy Spirit.',
            'refs'  => 'Joel 2:28–29; Acts 2:3–4, 38–39; Romans 12:6–8; Galatians 5:22–23; 1 Corinthians 12:8–11',
        ],
        [
            'title' => 'Divine Healing',
            'body'  => 'We believe that the healing of sicknesses and diseases is provided for God\'s people in the atonement. The Church is not, however, opposed to medication administered by qualified medical practitioners.',
            'refs'  => 'Isaiah 53:4–5; Matthew 8:7–17; Mark 16:17–18; Acts 10:38; James 5:14–16',
        ],
        [
            'title' => 'Tithes & Offerings',
            'body'  => 'We believe in tithing and in the giving of freewill offerings towards the furtherance of the cause of the Kingdom of God, and that God blesses a cheerful giver.',
            'refs'  => 'Genesis 14:18–20; Malachi 3:6–10; Matthew 23:23; 2 Corinthians 9:1–9; Hebrews 7:1–4',
        ],
        [
            'title' => 'The Second Coming & the Next Life',
            'body'  => 'We believe in the second coming of Christ and the resurrection of the dead, both the saved and the unsaved — they that are saved to the resurrection of life, and the unsaved to the resurrection of damnation.',
            'refs'  => 'Daniel 12:2; Mark 13:26; John 5:28–29; Acts 1:11, 10:42; Romans 2:7–11',
        ],
        [
            'title' => 'Marriage & Family Life',
            'body'  => 'We believe in the institution of marriage as a union established and ordained by God for the lifelong, intimate relationship between a man as husband and a woman as wife. We believe God instituted marriage primarily for mutual help, fellowship and comfort, and for the honourable procreation of children and their training in love, obedience to the Lord and responsible citizenship.',
            'refs'  => 'Genesis 2:18, 2:21–25; Matthew 19:4–6; 1 Corinthians 7:1–2',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Values
    |--------------------------------------------------------------------------
    */
    'core_values' => [
        [
            'title' => 'Evangelism',
            'body'  => 'The presentation of Jesus Christ in the power of the Holy Spirit, so that people come to trust Him as Saviour and Lord and serve Him in the fellowship of the Church. Evangelism is the responsibility of every member and the prime duty of every believer after conversion.',
        ],
        [
            'title' => 'Discipleship',
            'body'  => 'Training believers to be like Christ, with emphasis on holiness, righteousness, faithfulness, honesty, sincerity, humility, prayerfulness and disciplined, responsible living — through personal mentoring and systematic teaching of the word.',
        ],
        [
            'title' => 'The Holy Spirit',
            'body'  => 'The Christian life can be led only by the grace of the Holy Spirit. The new birth is His work, and the baptism of the Spirit gives power to serve and gifts that build the body of Christ. His leading in every sphere of church life is paramount.',
        ],
        [
            'title' => 'Ministry Excellence',
            'body'  => 'We seek to honour God, who gave His very best in the Saviour, by maintaining a high standard of excellence in all our ministries and activities.',
            'refs'  => 'Colossians 3:23–24',
        ],
        [
            'title' => 'Leadership',
            'body'  => 'Leadership development rests on the apostolic foundation. Appointments and callings are based on character, charisma and the leading of the Holy Spirit, and grow from the grassroots upward. Ministry is by both clergy and laity.',
        ],
        [
            'title' => 'Holiness',
            'body'  => 'The Church upholds the holiness of its members and officers unto the Lord in all their endeavours.',
            'refs'  => 'Romans 12:1; Hebrews 12:14',
        ],
        [
            'title' => 'Consistent Bible Teaching',
            'body'  => 'We devote ourselves to the apostles\' teaching — regular, systematic exposition of Scripture applied to real life.',
            'refs'  => 'Acts 2:42',
        ],
        [
            'title' => 'Church Discipline',
            'body'  => 'Respect for and obedience to authority, submission to the corrective measures of the Church, and the regular fellowship of the saints.',
            'refs'  => '2 Timothy 3:16–17; Hebrews 12:7–11; Acts 2:42–47; Hebrews 10:25',
        ],
        [
            'title' => 'Tithes & Offerings',
            'body'  => 'Faithfulness in giving offerings and paying tithes to enhance the ministry of the Church. The Church and its members depend solely on God as the source of financial supply.',
        ],
        [
            'title' => 'Social Responsibility',
            'body'  => 'We believe in communal living, with members supporting one another and serving the wider community — through health, education and practical care for those in need.',
        ],
        [
            'title' => 'Church Culture',
            'body'  => 'Distinctive attributes mark us out: a self-supporting attitude, faithfulness and integrity, distinctiveness in prayer, impartial discipline, mutual respect without discrimination of tribe, race or nationality, sacrificial service, total abstinence from alcohol, tobacco and hard drugs, and a commitment to church planting.',
        ],
        [
            'title' => 'Core Practices',
            'body'  => 'Regular prayer for the baptism of the Holy Spirit with the initial evidence of speaking in tongues; emphasis on the fruit and gifts of the Spirit; prayer for healing and deliverance; and services that are truly Pentecostal in praise, worship, teaching, testimony and the exercise of gifts.',
        ],
    ],
];
