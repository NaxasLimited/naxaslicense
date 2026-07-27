# Domain policy
Input is parsed only to a hostname, lowercased, stripped of scheme/path/query/fragment/default ports/trailing dot, then hostname/IP validated. `www.` and bare forms share capacity. localhost, 127.0.0.1, ::1, `.test`, and `.local` are non-production. Single Site permits one unrelated production canonical hostname; transfer is deliberately not implemented.
