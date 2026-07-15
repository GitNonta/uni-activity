from locust import HttpUser, task, between
import random

class WebsiteUser(HttpUser):
    wait_time = between(1, 5)

    @task
    def load_test(self):
        # สุ่ม IP Address
        random_ip = f"{random.randint(1,255)}.{random.randint(1,255)}.{random.randint(1,255)}.{random.randint(1,255)}"
        
        # ส่ง Header เพื่อพยายามหลอกว่าเป็น IP ต่างกัน
        headers = {
            "X-Forwarded-For": random_ip,
            "X-Real-IP": random_ip
        }
        
        self.client.get("/", headers=headers)
