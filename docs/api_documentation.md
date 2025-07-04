# IPMS API Documentation

## Overview
The IPMS (Integrated Property Management System) API provides RESTful endpoints for managing insurance policies, payments, and claims.

## Base URL
`https://your-domain.com/ipmsystem/backend/api`

## Authentication
All API endpoints require authentication. Include the following header:
```
Authorization: Bearer <your-jwt-token>
```

## Rate Limiting
- 100 requests per minute per IP address
- 1000 requests per hour per IP address

## Error Responses
All error responses will be in JSON format:
```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Error description",
        "details": "Additional error details"
    }
}
```

## Endpoints

### 1. Policy Management

#### Create Policy
```
POST /api/policies
```

Request Body:
```json
{
    "client_id": "int",
    "product_type": "string",
    "coverage_amount": "decimal",
    "start_date": "date",
    "end_date": "date"
}
```

Response:
```json
{
    "success": true,
    "data": {
        "policy_id": "int",
        "policy_number": "string"
    }
}
```

#### Get Policy
```
GET /api/policies/{policy_id}
```

Response:
```json
{
    "success": true,
    "data": {
        "policy_id": "int",
        "policy_number": "string",
        "client_id": "int",
        "product_type": "string",
        "coverage_amount": "decimal",
        "start_date": "date",
        "end_date": "date",
        "status": "string"
    }
}
```

### 2. Payment Management

#### Record Payment
```
POST /api/payments
```

Request Body:
```json
{
    "policy_id": "int",
    "amount": "decimal",
    "payment_date": "date",
    "payment_method": "string",
    "notes": "string"
}
```

Response:
```json
{
    "success": true,
    "data": {
        "payment_id": "int",
        "transaction_number": "string"
    }
}
```

### 3. Claim Management

#### Record Claim
```
POST /api/claims
```

Request Body:
```json
{
    "policy_id": "int",
    "claim_type": "string",
    "description": "string",
    "amount": "decimal",
    "date": "date"
}
```

Response:
```json
{
    "success": true,
    "data": {
        "claim_id": "int",
        "claim_number": "string"
    }
}
```

## Security

### CSRF Protection
All POST requests must include a CSRF token:
```
X-CSRF-Token: <token>
```

### Input Validation
- All date fields must be in YYYY-MM-DD format
- Amount fields must be decimal numbers
- Required fields must be provided
- Policy IDs and client IDs must exist

## Response Codes
- 200: Success
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Internal Server Error
