# CoopCycle Recommendation Engine

A self-hosted machine learning recommendation service. No customer data is sent to any third-party service — all computation happens on your own infrastructure.

## How it works

The engine uses **user-based collaborative filtering**:

1. **Training** — Order history is collected from the database. For each customer, we record which products or restaurants they ordered (and how many times). This builds a *user-item interaction matrix* where rows are customers and columns are products/restaurants, with values weighted by `log(1 + order_count)` to dampen the effect of very frequent orders.

2. **Finding similar customers** — We use scikit-learn's `NearestNeighbors` with cosine similarity to find customers whose ordering patterns are closest to a given customer.

3. **Generating recommendations** — Products/restaurants ordered by those similar customers (but not yet ordered by the target customer) are ranked by a weighted score and returned as recommendations.

4. **Cold-start fallback** — For new customers with no order history, the engine returns the most popular items at the relevant restaurant.

5. **Restaurant scoping** — At training time, a `product_restaurant_map` is built so that product recommendations can be filtered to items belonging to the restaurant the customer is currently ordering from.

## Multi-tenancy

One instance of this service can serve multiple CoopCycle instances. Each instance identifies itself with an `instance` parameter (its database name). Models are stored and retrieved per instance under `/models/{instance}/`.

## API

| Endpoint | Description |
|----------|-------------|
| `GET /recommendations?instance=&customer=&type=product\|restaurant&n=5&restaurant=` | Get recommendations for a customer |
| `POST /train/start` | Begin a training session for an instance |
| `POST /train/push` | Push a chunk of interaction data (called repeatedly) |
| `POST /train/commit` | Fit the model from pushed data and persist it |
| `GET /health` | Instance metadata and loaded model status |

## Training

Training is triggered by a Symfony console command that streams order history from the database in chunks of 1 000 rows:

```bash
docker compose exec php php bin/console coopcycle:recommender:train
```

Schedule this daily (e.g. 3 AM) via cron on the PHP container.

## Stack

- **Python 3.12** + **FastAPI** + **uvicorn**
- **scikit-learn** (`NearestNeighbors`, cosine metric)
- **scipy** sparse matrices (`csr_matrix`)
- **joblib** for model persistence

## Docker

The service runs as an internal container with no port exposed to the host. PHP calls it over the Docker bridge at `http://recommender:8000`. Configure the URL via the `COOPCYCLE_RECOMMENDER_URL` environment variable for non-Docker deployments.
