@extends('layouts.app', ['searchBar' => false])

@section('title', 'Terms of Service — AbangananHub')
@section('themeable', '1')

@section('content')
    <x-legal-page
        eyebrow="Terms of Service"
        title="The ground rules for renting on AbangananHub."
        updated="September 19, 2026"
        intro="These terms govern your use of AbangananHub, a rental marketplace for the Cebu area. By creating an account or using the platform, you agree to them. If you do not agree, please do not use the platform."
        :sections="[
            [
                'title' => 'Acceptance of these terms',
                'body' => [
                    'By accessing or using AbangananHub you confirm that you have read, understood and agree to these Terms of Service and to our Privacy Policy. We may update the terms from time to time; continuing to use the platform after an update means you accept the new version.',
                ],
            ],
            [
                'title' => 'Eligibility and accounts',
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
                'body' => [
                    'AbangananHub is a marketplace that helps tenants find rentals and helps landlords list them. We are not a landlord, agent, broker or party to any lease. Any rental agreement is strictly between the tenant and the landlord. We verify documents and review reports to make the platform safer, but we do not guarantee any listing, landlord or tenant, and “Verified” means only that the required documents were reviewed and approved.',
                ],
            ],
            [
                'title' => 'Tenants',
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
                'body' => [
                    'A reservation request is not a confirmed tenancy until the landlord approves it and the parties complete the steps shown on the platform. Where payments are made through the platform, they are processed by a third-party payment provider and subject to its terms. Fees, deposits and refunds are governed by the agreement between the tenant and landlord and by any policy stated on the listing. We are not responsible for payments made outside the platform.',
                ],
            ],
            [
                'title' => 'Messaging and conduct',
                'body' => [
                    'Keep communication respectful and relevant to renting. Do not harass, threaten or spam other users, and do not share another person’s private information without their permission. Messages may be reviewed when a report is filed or when needed to keep the platform safe.',
                ],
            ],
            [
                'title' => 'Prohibited activities',
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
                'body' => [
                    'You can report a listing, user or conversation through the platform. We review reports and may warn, restrict, suspend or permanently remove accounts and content that breach these terms or put others at risk. We may act without notice where necessary to protect users or comply with the law.',
                ],
            ],
            [
                'title' => 'Content you provide',
                'body' => [
                    'You keep ownership of the photos, text and other content you upload. By posting it, you give AbangananHub a non-exclusive, worldwide, royalty-free license to host, display and promote it on the platform for as long as it is published. You confirm you have the right to grant this license. The platform’s design, branding and code belong to AbangananHub and may not be copied without permission.',
                ],
            ],
            [
                'title' => 'Disclaimers',
                'body' => [
                    'The platform is provided “as is” and “as available”. To the fullest extent the law allows, we do not warrant that it will be uninterrupted or error-free, or that listings, prices, descriptions or user statements are accurate or complete. Always do your own checks before renting or paying.',
                ],
            ],
            [
                'title' => 'Limitation of liability',
                'body' => [
                    'To the fullest extent permitted by law, AbangananHub is not liable for indirect, incidental or consequential losses, or for losses arising from dealings between users, including disputes over rent, deposits, property condition or conduct. Nothing in these terms limits liability that cannot be limited under Philippine law.',
                ],
            ],
            [
                'title' => 'Ending your account',
                'body' => [
                    'You can stop using the platform at any time and ask us to close your account. We may suspend or end access if you breach these terms. Sections that by their nature should continue after closure, such as content licenses, disclaimers and liability limits, will continue to apply.',
                ],
            ],
            [
                'title' => 'Governing law',
                'body' => [
                    'These terms are governed by the laws of the Republic of the Philippines. Any dispute that cannot be settled amicably will be brought before the proper courts of Cebu City, unless the law requires otherwise.',
                ],
            ],
            [
                'title' => 'Changes and contact',
                'body' => [
                    'We may revise these terms as the platform evolves; the “Last updated” date shows the current version, and we will notify you of significant changes. For questions about these terms, contact the AbangananHub team through Report a Problem in your account.',
                ],
            ],
        ]" />
@endsection
