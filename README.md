# PasarKoin API

PasarKoin API is the backend service for a collectible coin marketplace.

The platform allows sellers to publish collectible coins from any country, buyers to browse and order available coins, and sellers to manage the complete order fulfilment process.

This repository contains the REST API built with Laravel. The React frontend is maintained in a separate repository.

## Related Repository

- [PasarKoin Frontend](https://github.com/JaydenLotan/pasarkoin-frontend)

## Features

### Authentication

- Register as a buyer or seller
- Login and logout
- Token-based authentication using Laravel Sanctum
- Retrieve the currently authenticated user
- Role-based route protection for buyers, sellers, and administrators

### Public Marketplace

- Browse available collectible coin listings
- View individual coin details
- Paginated listing responses
- View seller and coin image information

### Seller Features

- Create coin listings
- View personal listings
- Edit available listings
- Delete available listings
- Upload multiple coin images
- Select a primary image
- Delete uploaded images
- View orders received from buyers
- Add seller notes
- Update order status

Sold listings cannot be edited or deleted.

### Buyer Features

- Place an order for an available coin
- View personal order history
- View order status updates
- View notes provided by the seller

A buyer cannot order their own listing.

### Order Workflow

Orders follow this lifecycle:

```text
Pending → Confirmed → Shipped → Completed
