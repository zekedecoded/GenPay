const merchantCtx = document.getElementById("merchantSalesChart");

if (merchantCtx) {
  new Chart(merchantCtx, {
    type: "line",
    data: {
      labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
      datasets: [
        {
          label: "Sales",
          data: [0, 0, 0, 0, 0, 0, 0],
          borderColor: "#1e5c3a",
          backgroundColor: "rgba(30, 92, 58, 0.12)",
          borderWidth: 3,
          tension: 0.42,
          fill: true,
          pointRadius: 5,
          pointHoverRadius: 7,
          pointBackgroundColor: "#f0a500",
          pointBorderColor: "#1e5c3a",
          pointBorderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: "#14251b",
            font: {
              weight: "700",
            },
          },
        },
      },
      scales: {
        x: {
          ticks: {
            color: "#6b7a70",
          },
          grid: {
            color: "rgba(13, 36, 24, 0.06)",
          },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: "#6b7a70",
          },
          grid: {
            color: "rgba(13, 36, 24, 0.06)",
          },
        },
      },
    },
  });
}
