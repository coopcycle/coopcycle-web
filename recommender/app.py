import json
import os
import threading
from contextlib import asynccontextmanager

from apscheduler.schedulers.background import BackgroundScheduler
from fastapi import BackgroundTasks, FastAPI, HTTPException, Query

MODEL_DIR = os.environ.get("MODEL_DIR", "/models")

_models: dict = {"product": None, "restaurant": None}
_lock = threading.Lock()
_training = False


def _models_exist() -> bool:
    return all(
        os.path.exists(f"{MODEL_DIR}/{k}_recommender.joblib")
        for k in ("product", "restaurant")
    )


def _load_models() -> None:
    from recommender import CollaborativeFilteringRecommender

    with _lock:
        for kind in ("product", "restaurant"):
            path = f"{MODEL_DIR}/{kind}_recommender.joblib"
            if os.path.exists(path):
                _models[kind] = CollaborativeFilteringRecommender.load(path)
                print(f"Loaded {kind} model from {path}")


def _run_training() -> None:
    global _training
    _training = True
    try:
        import train as train_module
        train_module.train()
        _load_models()
    except Exception as e:
        print(f"Training failed: {e}")
    finally:
        _training = False


@asynccontextmanager
async def lifespan(app: FastAPI):
    if not _models_exist():
        print("No trained models found — starting initial training in background...")
        threading.Thread(target=_run_training, daemon=True).start()
    else:
        _load_models()

    scheduler = BackgroundScheduler()
    scheduler.add_job(_run_training, "cron", hour=3, minute=0)
    scheduler.start()

    yield

    scheduler.shutdown(wait=False)


app = FastAPI(title="CoopCycle Recommender", version="1.0.0", lifespan=lifespan)


@app.get("/health")
def health():
    metadata_path = f"{MODEL_DIR}/metadata.json"
    metadata: dict = {}
    if os.path.exists(metadata_path):
        with open(metadata_path) as f:
            metadata = json.load(f)
    return {
        "status": "ok",
        "models_loaded": _models["product"] is not None and _models["restaurant"] is not None,
        "training_in_progress": _training,
        **metadata,
    }


@app.get("/recommendations")
def recommendations(
    customer: str = Query(..., description="Customer IRI e.g. /api/customers/1"),
    type: str = Query(..., description="'product' or 'restaurant'"),
    n: int = Query(5, ge=1, le=20),
):
    if type not in ("product", "restaurant"):
        raise HTTPException(status_code=400, detail="type must be 'product' or 'restaurant'")

    model = _models.get(type)
    if model is None:
        if _training:
            raise HTTPException(status_code=503, detail="Model training in progress, please retry shortly")
        raise HTTPException(status_code=503, detail="Model not loaded. Trigger POST /train to train.")

    try:
        customer_id = int(customer.rstrip("/").split("/")[-1])
    except (ValueError, IndexError):
        raise HTTPException(status_code=400, detail="Invalid customer IRI — expected /api/customers/{id}")

    item_ids = model.recommend(customer_id, n=n)

    prefix = "/api/products" if type == "product" else "/api/restaurants"
    return {"recommendations": [f"{prefix}/{item_id}" for item_id in item_ids]}


@app.post("/train", status_code=202)
def trigger_training(background_tasks: BackgroundTasks):
    if _training:
        return {"message": "Training already in progress"}
    background_tasks.add_task(_run_training)
    return {"message": "Training started in background"}
