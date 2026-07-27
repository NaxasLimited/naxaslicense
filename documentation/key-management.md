# Key management
Configure only an absolute private-key file path outside the repository and public root. Symlinks, unreadable files, non-RSA keys, and RSA keys below 3072 bits are rejected. Never put key material in env values, HTTP input, databases, output or logs. Provision a dedicated restricted test key outside the tree in CI and rotate operational keys with an audited deployment procedure.
