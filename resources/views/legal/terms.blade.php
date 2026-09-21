@extends('layouts.app', ['searchBar' => false])

@section('title', 'Terms of Service — AbangananHub')

@section('content')
    <x-legal-page
        eyebrow="Terms of Service"
        title="The ground rules for renting on AbangananHub."
        updated="September 19, 2026"
        intro="These terms govern your use of AbangananHub, a rental marketplace for the Cebu area. By creating an account or using the platform, you agree to them. If you do not agree, please do not use the platform."
        :sections="[
            [
                'title' => 'Acceptance of these terms',
                'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0Z',
                'body' => [
                    'By accessing or using AbangananHub you confirm that you have read, understood and agree to these Terms of Service and to our Privacy Policy. We may update the terms from time to time; continuing to use the platform after an update means you accept the new version.',
                ],
            ],
            [
                'title' => 'Eligibility and accounts',
                'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
                'body' => [
                    ['list' => [
                        'You must be at least 18 years old and able to enter into a binding rental agreement.',
                        'You must give accurate, current information when you register and keep it up to date.',
                        'You are responsible for all activity under your account and for keeping your password confidential. Tell us immediately if you suspect unauthorized use.',
                        'One person, one account. Do not impersonate anyone or create accounts to evade a suspension.',
                    ]],
                ],
            ],
            [
                'title' => 'What AbangananHub is (and is not)',
                'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21',
                'body' => [
                    'AbangananHub is a marketplace that helps tenants find rentals and helps landlords list them. We are not a landlord, agent, broker or party to any lease. Any rental agreement is strictly between the tenant and the landlord. We verify documents and review reports to make the platform safer, but we do not guarantee any listing, landlord or tenant, and “Verified” means only that the required documents were reviewed and approved.',
                ],
            ],
            [
                'title' => 'Tenants',
                'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
                'body' => [
                    ['list' => [
                        'Read each listing carefully and confirm details, house rules and charges with the landlord before you commit.',
                        'Inquiries and reservation requests must be genuine. Do not submit false or duplicate requests.',
                        'Visit the property and meet in a safe, public place before paying any deposit or rent. Never send money to someone you have not verified.',
                        'You are responsible for complying with the agreement you make with the landlord, including payments and house rules.',
                    ]],
                ],
            ],
            [
                'title' => 'Landlords and listings',
                'icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25Z',
                'body' => [
                    ['list' => [
                        'You must own the property or have the legal right to rent it out, and you must submit accurate verification documents.',
                        'Listings must be truthful and current: real photos of the actual unit, correct price, deposit, charges, availability and house rules.',
                        'Do not list a property that is unavailable, or discriminate against tenants on any unlawful basis.',
                        'We may review, request changes to, unpublish or remove any listing that is inaccurate, misleading or in breach of these terms.',
                        'You are responsible for your tenancy agreements, taxes, permits and any dealings with your tenants.',
                    ]],
                ],
            ],
            [
                'title' => 'Reservations, agreements and payments',
                'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
                'body' => [
                    'A reservation request is not a confirmed tenancy until the landlord approves it and the parties complete the steps shown on the platform. Where payments are made through the platform, they are processed by a third-party payment provider and subject to its terms. Fees, deposits and refunds are governed by the agreement between the tenant and landlord and by any policy stated on the listing. We are not responsible for payments made outside the platform.',
                ],
            ],
            [
                'title' => 'Messaging and conduct',
                'icon' => 'M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
                'body' => [
                    'Keep communication respectful and relevant to renting. Do not harass, threaten or spam other users, and do not share another person’s private information without their permission. Messages may be reviewed when a report is filed or when needed to keep the platform safe.',
                ],
            ],
            [
                'title' => 'Prohibited activities',
                'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                'body' => [
                    ['list' => [
                        'Posting false, misleading or fraudulent listings, reviews or reports.',
                        'Collecting payments for properties you do not control, or asking users to pay outside the platform to avoid its protections.',
                        'Uploading unlawful, offensive or infringing content, or malware.',
                        'Scraping, reverse-engineering, overloading or otherwise interfering with the platform or its security.',
                        'Using the platform for any purpose that is unlawful under Philippine law.',
                    ]],
                ],
            ],
            [
                'title' => 'Reports, moderation and suspension',
                'icon' => 'M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5',
                'body' => [
                    'You can report a listing, user or conversation through the platform. We review reports and may warn, restrict, suspend or permanently remove accounts and content that breach these terms or put others at risk. We may act without notice where necessary to protect users or comply with the law.',
                ],
            ],
            [
                'title' => 'Content you provide',
                'icon' => 'm2.25 15.75 5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0Z',
                'body' => [
                    'You keep ownership of the photos, text and other content you upload. By posting it, you give AbangananHub a non-exclusive, worldwide, royalty-free license to host, display and promote it on the platform for as long as it is published. You confirm you have the right to grant this license. The platform’s design, branding and code belong to AbangananHub and may not be copied without permission.',
                ],
            ],
            [
                'title' => 'Disclaimers',
                'icon' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0Zm-9-3.75h.008v.008H12V8.25Z',
                'body' => [
                    'The platform is provided “as is” and “as available”. To the fullest extent the law allows, we do not warrant that it will be uninterrupted or error-free, or that listings, prices, descriptions or user statements are accurate or complete. Always do your own checks before renting or paying.',
                ],
            ],
            [
                'title' => 'Limitation of liability',
                'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
                'body' => [
                    'To the fullest extent permitted by law, AbangananHub is not liable for indirect, incidental or consequential losses, or for losses arising from dealings between users, including disputes over rent, deposits, property condition or conduct. Nothing in these terms limits liability that cannot be limited under Philippine law.',
                ],
            ],
            [
                'title' => 'Ending your account',
                'icon' => 'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9',
                'body' => [
                    'You can stop using the platform at any time and ask us to close your account. We may suspend or end access if you breach these terms. Sections that by their nature should continue after closure, such as content licenses, disclaimers and liability limits, will continue to apply.',
                ],
            ],
            [
                'title' => 'Governing law',
                'icon' => 'M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z',
                'body' => [
                    'These terms are governed by the laws of the Republic of the Philippines. Any dispute that cannot be settled amicably will be brought before the proper courts of Cebu City, unless the law requires otherwise.',
                ],
            ],
            [
                'title' => 'Changes and contact',
                'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 18.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487Zm0 0L19.5 7.125',
                'body' => [
                    'We may revise these terms as the platform evolves; the “Last updated” date shows the current version, and we will notify you of significant changes. For questions about these terms, contact the AbangananHub team through Report a Problem in your account.',
                ],
            ],
        ]" />
@endsection
