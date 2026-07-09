import urllib.request
import concurrent.futures
import time
import statistics

URL = "http://192.168.1.222:8080/login"
TOTAL_REQUESTS = 200
CONCURRENT_USERS = 20

def fetch(url):
    start = time.time()
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'LoadTest/1.0'})
        with urllib.request.urlopen(req, timeout=10) as response:
            response.read()
            status = response.status
    except Exception as e:
        status = 0
    end = time.time()
    return status, (end - start) * 1000  # in ms

def main():
    print(f"--- Load Testing Server: {URL} ---")
    print(f"Simulating {CONCURRENT_USERS} concurrent users, total {TOTAL_REQUESTS} requests...\n")
    
    start_time = time.time()
    
    success = 0
    failed = 0
    response_times = []
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=CONCURRENT_USERS) as executor:
        futures = [executor.submit(fetch, URL) for _ in range(TOTAL_REQUESTS)]
        
        for i, future in enumerate(concurrent.futures.as_completed(futures)):
            status, duration = future.result()
            if status in [200, 302, 301]:
                success += 1
                response_times.append(duration)
            else:
                failed += 1
                
            if (i+1) % 20 == 0:
                print(f"Progress: {i+1}/{TOTAL_REQUESTS} requests completed.")

    total_time = time.time() - start_time
    
    print("\n--- Test Results ---")
    print(f"Total Time Taken : {total_time:.2f} seconds")
    print(f"Total Requests   : {TOTAL_REQUESTS}")
    print(f"Successful       : {success}")
    print(f"Failed/Errors    : {failed}")
    
    if success > 0:
        req_per_sec = TOTAL_REQUESTS / total_time
        print(f"Requests/Second  : {req_per_sec:.2f} req/s")
        print(f"Fastest Response : {min(response_times):.2f} ms")
        print(f"Slowest Response : {max(response_times):.2f} ms")
        print(f"Average Response : {statistics.mean(response_times):.2f} ms")
        print(f"Median Response  : {statistics.median(response_times):.2f} ms")
        
        if req_per_sec > 100:
            print("\n✅ Server performance is EXCELLENT for a mobile device!")
        elif req_per_sec > 30:
            print("\n⚠️ Server performance is GOOD. Can handle a moderate class size (~30-50 concurrent active users).")
        else:
            print("\n❌ Server performance is SLOW. Suggest reducing database queries or caching.")

if __name__ == "__main__":
    main()
